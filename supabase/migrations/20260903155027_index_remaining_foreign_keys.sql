create index if not exists idx_diaria_oficina_modular_reserva_oficina_modular_id
    on public.diaria_oficina_modular_reserva (oficina_modular_id);

create index if not exists idx_diaria_slots_travados_oficina_modular_id
    on public.diaria_slots_travados (oficina_modular_id);

create index if not exists idx_diaria_slots_travados_slot_id
    on public.diaria_slots_travados (slot_id);

create index if not exists idx_guardians_student_id
    on public.guardians (student_id);

create index if not exists idx_oficina_modular_auditoria_oficina_modular_id
    on public.oficina_modular_auditoria (oficina_modular_id);

create index if not exists idx_payments_student_id
    on public.payments (student_id);

create index if not exists idx_pendencia_de_cadastro_student_id
    on public.pendencia_de_cadastro (student_id);

create index if not exists idx_pendencia_tokens_pendencia_id
    on public.pendencia_tokens (pendencia_id);

create index if not exists idx_verification_tokens_guardian_id
    on public.verification_tokens (guardian_id);
