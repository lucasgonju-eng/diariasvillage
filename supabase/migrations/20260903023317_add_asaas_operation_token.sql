alter table public.payments
  add column if not exists asaas_operation_token text;

alter table public.payments
  drop constraint if exists payments_asaas_operation_token_valid;

alter table public.payments
  add constraint payments_asaas_operation_token_valid
  check (
    asaas_operation_token is null
    or asaas_operation_token ~ '^[0-9a-f]{32}$'
  );

create unique index if not exists uq_payments_asaas_operation_token
  on public.payments (asaas_operation_token)
  where asaas_operation_token is not null;

comment on column public.payments.asaas_operation_token is
'Token único da tentativa remota em processing_asaas; permite reconciliar resposta ambígua sem reutilizar referência histórica.';
