alter table public.payments
  add column if not exists idempotency_key text;

create unique index if not exists uq_payments_idempotency_key
  on public.payments (idempotency_key)
  where idempotency_key is not null;

create unique index if not exists uq_payments_asaas_payment_id
  on public.payments (asaas_payment_id)
  where asaas_payment_id is not null;
