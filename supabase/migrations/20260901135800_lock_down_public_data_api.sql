begin;

alter table public.students enable row level security;
alter table public.guardians enable row level security;
alter table public.verification_tokens enable row level security;
alter table public.payments enable row level security;
alter table public.pendencia_de_cadastro enable row level security;
alter table public.pendencia_tokens enable row level security;
alter table public.diaria enable row level security;
alter table public.oficina_modular enable row level security;
alter table public.oficina_modular_horarios enable row level security;
alter table public.grade_slots enable row level security;
alter table public.diaria_oficina_modular_reserva enable row level security;
alter table public.diaria_slots_travados enable row level security;
alter table public.oficina_modular_auditoria enable row level security;
alter table public.oficina_modular_upsell_log enable row level security;

revoke all privileges on table public.students from anon, authenticated;
revoke all privileges on table public.guardians from anon, authenticated;
revoke all privileges on table public.verification_tokens from anon, authenticated;
revoke all privileges on table public.payments from anon, authenticated;
revoke all privileges on table public.pendencia_de_cadastro from anon, authenticated;
revoke all privileges on table public.pendencia_tokens from anon, authenticated;
revoke all privileges on table public.diaria from anon, authenticated;
revoke all privileges on table public.oficina_modular from anon, authenticated;
revoke all privileges on table public.oficina_modular_horarios from anon, authenticated;
revoke all privileges on table public.grade_slots from anon, authenticated;
revoke all privileges on table public.diaria_oficina_modular_reserva from anon, authenticated;
revoke all privileges on table public.diaria_slots_travados from anon, authenticated;
revoke all privileges on table public.oficina_modular_auditoria from anon, authenticated;
revoke all privileges on table public.oficina_modular_upsell_log from anon, authenticated;

alter function public.oficina_modular_get_ocupacao(uuid, date, smallint)
  set search_path = public;
alter function public.oficina_modular_grade_confirmar_pagamento(uuid)
  set search_path = public;
alter function public.oficina_modular_grade_liberar_e_cancelar(uuid, uuid, character varying, boolean)
  set search_path = public;
alter function public.oficina_modular_grade_revalidar_checkout(uuid)
  set search_path = public;
alter function public.oficina_modular_grade_travar_e_reservar(uuid, uuid, smallint, character varying, boolean, smallint)
  set search_path = public;

revoke execute on function public.oficina_modular_get_ocupacao(uuid, date, smallint)
  from public, anon, authenticated;
revoke execute on function public.oficina_modular_grade_confirmar_pagamento(uuid)
  from public, anon, authenticated;
revoke execute on function public.oficina_modular_grade_liberar_e_cancelar(uuid, uuid, character varying, boolean)
  from public, anon, authenticated;
revoke execute on function public.oficina_modular_grade_revalidar_checkout(uuid)
  from public, anon, authenticated;
revoke execute on function public.oficina_modular_grade_travar_e_reservar(uuid, uuid, smallint, character varying, boolean, smallint)
  from public, anon, authenticated;

grant execute on function public.oficina_modular_get_ocupacao(uuid, date, smallint)
  to service_role;
grant execute on function public.oficina_modular_grade_confirmar_pagamento(uuid)
  to service_role;
grant execute on function public.oficina_modular_grade_liberar_e_cancelar(uuid, uuid, character varying, boolean)
  to service_role;
grant execute on function public.oficina_modular_grade_revalidar_checkout(uuid)
  to service_role;
grant execute on function public.oficina_modular_grade_travar_e_reservar(uuid, uuid, smallint, character varying, boolean, smallint)
  to service_role;

alter default privileges in schema public
  revoke all privileges on tables from anon, authenticated;
alter default privileges in schema public
  revoke execute on functions from public, anon, authenticated;

commit;
