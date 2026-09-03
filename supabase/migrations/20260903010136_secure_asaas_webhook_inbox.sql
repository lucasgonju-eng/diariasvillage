alter table public.pendencia_de_cadastro
  add column if not exists verified_at timestamptz,
  add column if not exists asaas_payment_id text,
  add column if not exists asaas_invoice_url text;

create unique index if not exists uq_pendencia_asaas_payment_id
  on public.pendencia_de_cadastro (asaas_payment_id)
  where asaas_payment_id is not null;

create table if not exists public.asaas_webhook_events (
  event_id text primary key,
  event_type text not null,
  payment_id text not null,
  payload jsonb not null,
  payload_sha256 text not null,
  status text not null default 'RECEIVED',
  delivery_count integer not null default 1,
  attempt_count integer not null default 0,
  received_at timestamptz not null default now(),
  last_received_at timestamptz not null default now(),
  locked_at timestamptz,
  processed_at timestamptz,
  last_error text,
  constraint asaas_webhook_events_event_id_format
    check (event_id ~ '^evt_[A-Za-z0-9]+$'),
  constraint asaas_webhook_events_event_type_format
    check (event_type ~ '^[A-Z][A-Z0-9_]{1,79}$'),
  constraint asaas_webhook_events_payment_id_format
    check (payment_id ~ '^pay_[A-Za-z0-9]+$'),
  constraint asaas_webhook_events_payload_sha256_format
    check (payload_sha256 ~ '^[a-f0-9]{64}$'),
  constraint asaas_webhook_events_status_valid
    check (status in ('RECEIVED', 'PROCESSING', 'PROCESSED', 'IGNORED', 'FAILED')),
  constraint asaas_webhook_events_delivery_count_positive
    check (delivery_count > 0),
  constraint asaas_webhook_events_attempt_count_nonnegative
    check (attempt_count >= 0)
);

create index if not exists idx_asaas_webhook_events_payment
  on public.asaas_webhook_events (payment_id, received_at desc);

create index if not exists idx_asaas_webhook_events_retry
  on public.asaas_webhook_events (status, last_received_at)
  where status in ('RECEIVED', 'FAILED');

create or replace function public.claim_asaas_webhook_event(
  p_event_id text,
  p_event_type text,
  p_payment_id text,
  p_payload jsonb,
  p_payload_sha256 text
)
returns jsonb
language plpgsql
set search_path = public
as $$
declare
  v_inserted integer := 0;
  v_event public.asaas_webhook_events%rowtype;
begin
  if p_event_id !~ '^evt_[A-Za-z0-9]+$'
     or p_event_type !~ '^[A-Z][A-Z0-9_]{1,79}$'
     or p_payment_id !~ '^pay_[A-Za-z0-9]+$'
     or p_payload is null
     or p_payload_sha256 !~ '^[a-f0-9]{64}$' then
    return jsonb_build_object('ok', false, 'code', 'INVALID_EVENT');
  end if;

  insert into public.asaas_webhook_events (
    event_id,
    event_type,
    payment_id,
    payload,
    payload_sha256
  ) values (
    p_event_id,
    p_event_type,
    p_payment_id,
    p_payload,
    p_payload_sha256
  )
  on conflict (event_id) do nothing;
  get diagnostics v_inserted = row_count;

  select *
  into v_event
  from public.asaas_webhook_events
  where event_id = p_event_id
  for update;

  if v_event.payload_sha256 <> p_payload_sha256
     or v_event.event_type <> p_event_type
     or v_event.payment_id <> p_payment_id then
    update public.asaas_webhook_events
    set delivery_count = delivery_count + 1,
        last_received_at = now(),
        last_error = 'Evento repetido com conteúdo divergente.'
    where event_id = p_event_id;

    return jsonb_build_object('ok', false, 'code', 'EVENT_PAYLOAD_CONFLICT');
  end if;

  if v_inserted = 0 then
    update public.asaas_webhook_events
    set delivery_count = delivery_count + 1,
        last_received_at = now()
    where event_id = p_event_id
    returning * into v_event;
  end if;

  if v_event.status in ('PROCESSED', 'IGNORED') then
    return jsonb_build_object(
      'ok', true,
      'claimed', false,
      'idempotent', true,
      'status', v_event.status
    );
  end if;

  if v_event.status = 'PROCESSING'
     and v_event.locked_at is not null
     and v_event.locked_at >= now() - interval '5 minutes' then
    return jsonb_build_object(
      'ok', true,
      'claimed', false,
      'idempotent', true,
      'status', 'PROCESSING'
    );
  end if;

  update public.asaas_webhook_events
  set status = 'PROCESSING',
      attempt_count = attempt_count + 1,
      locked_at = now(),
      last_error = null
  where event_id = p_event_id
  returning * into v_event;

  return jsonb_build_object(
    'ok', true,
    'claimed', true,
    'idempotent', false,
    'attempt_count', v_event.attempt_count
  );
end;
$$;

create or replace function public.complete_asaas_webhook_event(
  p_event_id text,
  p_status text default 'PROCESSED'
)
returns jsonb
language plpgsql
set search_path = public
as $$
declare
  v_updated integer := 0;
begin
  if p_status not in ('PROCESSED', 'IGNORED') then
    return jsonb_build_object('ok', false, 'code', 'INVALID_STATUS');
  end if;

  update public.asaas_webhook_events
  set status = p_status,
      processed_at = now(),
      locked_at = null,
      last_error = null
  where event_id = p_event_id
    and status = 'PROCESSING';
  get diagnostics v_updated = row_count;

  return jsonb_build_object('ok', v_updated = 1, 'updated', v_updated = 1);
end;
$$;

create or replace function public.fail_asaas_webhook_event(
  p_event_id text,
  p_error text
)
returns jsonb
language plpgsql
set search_path = public
as $$
declare
  v_updated integer := 0;
begin
  update public.asaas_webhook_events
  set status = 'FAILED',
      locked_at = null,
      last_error = left(coalesce(p_error, 'Falha não especificada.'), 1000)
  where event_id = p_event_id
    and status = 'PROCESSING';
  get diagnostics v_updated = row_count;

  return jsonb_build_object('ok', v_updated = 1, 'updated', v_updated = 1);
end;
$$;

alter table public.asaas_webhook_events enable row level security;

revoke all on table public.asaas_webhook_events from anon, authenticated;
revoke execute on function public.claim_asaas_webhook_event(text, text, text, jsonb, text)
  from public, anon, authenticated;
revoke execute on function public.complete_asaas_webhook_event(text, text)
  from public, anon, authenticated;
revoke execute on function public.fail_asaas_webhook_event(text, text)
  from public, anon, authenticated;

grant execute on function public.claim_asaas_webhook_event(text, text, text, jsonb, text)
  to service_role;
grant execute on function public.complete_asaas_webhook_event(text, text)
  to service_role;
grant execute on function public.fail_asaas_webhook_event(text, text)
  to service_role;
