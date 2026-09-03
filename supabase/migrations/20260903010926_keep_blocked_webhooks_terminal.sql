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

  if v_event.status in ('PROCESSED', 'IGNORED', 'BLOCKED') then
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

revoke execute on function public.claim_asaas_webhook_event(text, text, text, jsonb, text)
  from public, anon, authenticated;
grant execute on function public.claim_asaas_webhook_event(text, text, text, jsonb, text)
  to service_role;
