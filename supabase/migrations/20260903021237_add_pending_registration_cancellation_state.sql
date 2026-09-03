alter table public.pendencia_de_cadastro
  add column if not exists status text not null default 'pending',
  add column if not exists canceled_at timestamptz,
  add column if not exists cancel_reason text;

update public.pendencia_de_cadastro
set status = 'paid'
where paid_at is not null
  and status = 'pending';

alter table public.pendencia_de_cadastro
  drop constraint if exists pendencia_de_cadastro_status_valid;

alter table public.pendencia_de_cadastro
  add constraint pendencia_de_cadastro_status_valid
  check (status in ('pending', 'paid', 'canceled'));

create index if not exists idx_pendencia_status_created_at
  on public.pendencia_de_cadastro (status, created_at desc);

comment on column public.pendencia_de_cadastro.status is
'Estado local preservado: pending, paid ou canceled. Registros financeiros não devem ser apagados fisicamente.';

comment on column public.pendencia_de_cadastro.canceled_at is
'Instante em que o cancelamento remoto foi confirmado ou a ausência remota foi comprovada.';

comment on column public.pendencia_de_cadastro.cancel_reason is
'Motivo administrativo auditável do cancelamento lógico.';
