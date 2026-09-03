create or replace function public.enforce_pendencia_financial_state()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
begin
  if tg_op = 'UPDATE' and old.status = 'canceled' then
    raise exception 'PENDENCIA_CANCELED_IS_TERMINAL';
  end if;

  if new.status = 'canceled' then
    if new.paid_at is not null or (tg_op = 'UPDATE' and old.status = 'paid') then
      raise exception 'PAID_PENDENCIA_CANNOT_BE_CANCELED';
    end if;
    if nullif(trim(new.cancel_reason), '') is null then
      raise exception 'PENDENCIA_CANCEL_REASON_REQUIRED';
    end if;
    new.canceled_at := coalesce(new.canceled_at, now());
  elsif new.paid_at is not null then
    new.status := 'paid';
    new.canceled_at := null;
    new.cancel_reason := null;
  elsif new.status = 'paid' then
    raise exception 'PAID_PENDENCIA_REQUIRES_PAID_AT';
  end if;

  return new;
end;
$$;

drop trigger if exists trg_enforce_pendencia_financial_state
  on public.pendencia_de_cadastro;

create trigger trg_enforce_pendencia_financial_state
before insert or update on public.pendencia_de_cadastro
for each row execute function public.enforce_pendencia_financial_state();

revoke all on function public.enforce_pendencia_financial_state() from public, anon, authenticated;
grant execute on function public.enforce_pendencia_financial_state() to service_role;

drop index if exists public.uq_payments_one_open_per_diaria;

create unique index uq_payments_one_open_per_diaria
on public.payments (diaria_id)
where diaria_id is not null
  and paid_at is null
  and lower(status) in (
    'queued',
    'processing_asaas',
    'pending',
    'pending_asaas',
    'overdue',
    'awaiting_risk_analysis'
  );
