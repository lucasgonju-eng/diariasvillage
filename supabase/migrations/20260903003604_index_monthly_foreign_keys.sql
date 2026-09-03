create index if not exists idx_monthly_student_plans_updated_by
  on public.monthly_student_plans (updated_by)
  where updated_by is not null;

create index if not exists idx_monthly_submission_unlocked_by
  on public.monthly_workshop_submissions (unlocked_by)
  where unlocked_by is not null;

create index if not exists idx_monthly_slots_workshop
  on public.monthly_workshop_slots (oficina_modular_id)
  where oficina_modular_id is not null;

create index if not exists idx_monthly_slots_schedule
  on public.monthly_workshop_slots (horario_id)
  where horario_id is not null;

create index if not exists idx_monthly_entries_submission
  on public.monthly_workshop_entries (submission_id);

create index if not exists idx_monthly_entries_guardian
  on public.monthly_workshop_entries (guardian_id, entry_date);
