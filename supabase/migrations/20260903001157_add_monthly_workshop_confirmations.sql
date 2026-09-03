alter table public.oficina_modular
  add column if not exists monthly_selection_mode text not null default 'ALL_MEETINGS';

do $$
begin
  if not exists (
    select 1
    from pg_constraint
    where conname = 'chk_oficina_modular_monthly_selection_mode'
  ) then
    alter table public.oficina_modular
      add constraint chk_oficina_modular_monthly_selection_mode
      check (monthly_selection_mode in ('ALL_MEETINGS', 'SINGLE_MEETING'));
  end if;
end
$$;

update public.oficina_modular
set monthly_selection_mode = 'SINGLE_MEETING'
where lower(trim(nome)) = lower('Trilhas do Conhecimento')
  and descricao ilike '%[CATALOGO_OM_MENSAL]%';

create table if not exists public.monthly_student_plans (
  student_id uuid primary key references public.students(id) on delete cascade,
  weekly_days smallint not null,
  active boolean not null default true,
  updated_by uuid references public.admin_users(id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint chk_monthly_student_plans_weekly_days
    check (weekly_days between 2 and 5)
);

create table if not exists public.monthly_workshop_submissions (
  id uuid primary key default gen_random_uuid(),
  student_id uuid not null references public.students(id) on delete restrict,
  guardian_id uuid not null references public.guardians(id) on delete restrict,
  reference_month date not null,
  weekly_days_snapshot smallint not null,
  required_slots smallint not null,
  status text not null default 'CONFIRMED',
  confirmed_at timestamptz not null default now(),
  unlocked_at timestamptz,
  unlocked_by uuid references public.admin_users(id) on delete set null,
  created_at timestamptz not null default now(),
  constraint chk_monthly_submission_month_start
    check (reference_month = date_trunc('month', reference_month)::date),
  constraint chk_monthly_submission_weekly_days
    check (weekly_days_snapshot between 2 and 5),
  constraint chk_monthly_submission_required_slots
    check (required_slots = weekly_days_snapshot * 2),
  constraint chk_monthly_submission_status
    check (status in ('CONFIRMED', 'UNLOCKED'))
);

create unique index if not exists uq_monthly_submission_confirmed
  on public.monthly_workshop_submissions (student_id, reference_month)
  where status = 'CONFIRMED';

create index if not exists idx_monthly_submission_guardian_month
  on public.monthly_workshop_submissions (guardian_id, reference_month desc);

create table if not exists public.monthly_workshop_slots (
  id uuid primary key default gen_random_uuid(),
  submission_id uuid not null references public.monthly_workshop_submissions(id) on delete cascade,
  oficina_modular_id uuid references public.oficina_modular(id) on delete restrict,
  horario_id uuid references public.oficina_modular_horarios(id) on delete restrict,
  orientadora boolean not null default false,
  dia_semana smallint not null,
  hora_inicio time not null,
  hora_fim time not null,
  created_at timestamptz not null default now(),
  constraint chk_monthly_slot_weekday
    check (dia_semana between 1 and 5),
  constraint chk_monthly_slot_time
    check (
      (hora_inicio = time '14:00' and hora_fim = time '15:00')
      or (hora_inicio = time '15:40' and hora_fim = time '16:40')
    ),
  constraint chk_monthly_slot_source
    check (
      (orientadora and oficina_modular_id is null and horario_id is null)
      or (not orientadora and oficina_modular_id is not null and horario_id is not null)
    )
);

create unique index if not exists uq_monthly_slot_time
  on public.monthly_workshop_slots (submission_id, dia_semana, hora_inicio);

create unique index if not exists uq_monthly_slot_schedule
  on public.monthly_workshop_slots (submission_id, horario_id)
  where horario_id is not null;

create table if not exists public.monthly_workshop_entries (
  id uuid primary key default gen_random_uuid(),
  submission_id uuid not null references public.monthly_workshop_submissions(id) on delete cascade,
  slot_id uuid not null references public.monthly_workshop_slots(id) on delete cascade,
  student_id uuid not null references public.students(id) on delete restrict,
  guardian_id uuid not null references public.guardians(id) on delete restrict,
  entry_date date not null,
  status text not null default 'CONFIRMED_BY_PLAN',
  access_code text not null,
  created_at timestamptz not null default now(),
  canceled_at timestamptz,
  constraint chk_monthly_entry_status
    check (status in ('CONFIRMED_BY_PLAN', 'CANCELED'))
);

create unique index if not exists uq_monthly_entry_slot_date
  on public.monthly_workshop_entries (slot_id, entry_date);

create index if not exists idx_monthly_entry_date
  on public.monthly_workshop_entries (entry_date, status);

create index if not exists idx_monthly_entry_student_date
  on public.monthly_workshop_entries (student_id, entry_date);

create or replace function public.confirm_monthly_workshops(
  p_guardian_id uuid,
  p_student_id uuid,
  p_reference_month date,
  p_choices jsonb
)
returns jsonb
language plpgsql
set search_path = public
as $$
declare
  v_plan public.monthly_student_plans%rowtype;
  v_submission_id uuid;
  v_existing_id uuid;
  v_month_end date;
  v_choice jsonb;
  v_schedule record;
  v_weekday smallint;
  v_start time;
  v_end time;
  v_required integer;
  v_count integer;
  v_day_count integer;
  v_invalid_group_count integer;
begin
  if p_reference_month is null
     or p_reference_month <> date_trunc('month', p_reference_month)::date then
    return jsonb_build_object('ok', false, 'code', 'INVALID_REFERENCE_MONTH', 'error', 'Competência mensal inválida.');
  end if;

  if jsonb_typeof(p_choices) <> 'array' then
    return jsonb_build_object('ok', false, 'code', 'INVALID_CHOICES', 'error', 'Seleções mensais inválidas.');
  end if;

  perform 1
  from public.guardians
  where id = p_guardian_id
    and student_id = p_student_id;
  if not found then
    return jsonb_build_object('ok', false, 'code', 'GUARDIAN_STUDENT_MISMATCH', 'error', 'Responsável não vinculado ao aluno.');
  end if;

  select *
  into v_plan
  from public.monthly_student_plans
  where student_id = p_student_id
    and active = true
  for update;
  if not found then
    return jsonb_build_object('ok', false, 'code', 'MONTHLY_PLAN_NOT_FOUND', 'error', 'Plano mensalista ativo não encontrado.');
  end if;

  select id
  into v_existing_id
  from public.monthly_workshop_submissions
  where student_id = p_student_id
    and reference_month = p_reference_month
    and status = 'CONFIRMED'
  limit 1;
  if v_existing_id is not null then
    return jsonb_build_object('ok', true, 'idempotent', true, 'submission_id', v_existing_id);
  end if;

  create temporary table if not exists monthly_choice_expansion (
    oficina_modular_id uuid,
    horario_id uuid,
    orientadora boolean not null,
    dia_semana smallint not null,
    hora_inicio time not null,
    hora_fim time not null
  ) on commit drop;
  truncate table monthly_choice_expansion;

  v_month_end := (p_reference_month + interval '1 month - 1 day')::date;

  for v_choice in select value from jsonb_array_elements(p_choices)
  loop
    if coalesce((v_choice->>'orientadora')::boolean, false) then
      v_weekday := (v_choice->>'dia_semana')::smallint;
      v_start := (v_choice->>'hora_inicio')::time;
      v_end := case
        when v_start = time '14:00' then time '15:00'
        when v_start = time '15:40' then time '16:40'
        else null
      end;
      if v_weekday not between 1 and 5 or v_end is null then
        return jsonb_build_object('ok', false, 'code', 'INVALID_ORIENTATION_SLOT', 'error', 'Horário da Orientadora inválido.');
      end if;
      insert into monthly_choice_expansion
        (oficina_modular_id, horario_id, orientadora, dia_semana, hora_inicio, hora_fim)
      values (null, null, true, v_weekday, v_start, v_end);
      continue;
    end if;

    select
      o.id as oficina_id,
      h.id as horario_id,
      h.dia_semana,
      h.hora_inicio,
      h.hora_fim
    into v_schedule
    from public.oficina_modular_horarios h
    join public.oficina_modular o on o.id = h.oficina_modular_id
    where h.id = (v_choice->>'horario_id')::uuid
      and o.ativa = true
      and o.descricao ilike '%[CATALOGO_OM_MENSAL]%'
      and coalesce(o.data_inicio_validade, p_reference_month) <= v_month_end
      and coalesce(o.data_fim_validade, v_month_end) >= p_reference_month;
    if not found then
      return jsonb_build_object('ok', false, 'code', 'INVALID_WORKSHOP_SLOT', 'error', 'Encontro de oficina inválido para a competência.');
    end if;

    insert into monthly_choice_expansion
      (oficina_modular_id, horario_id, orientadora, dia_semana, hora_inicio, hora_fim)
    values (
      v_schedule.oficina_id,
      v_schedule.horario_id,
      false,
      v_schedule.dia_semana,
      v_schedule.hora_inicio,
      v_schedule.hora_fim
    );
  end loop;

  if exists (
    select 1
    from monthly_choice_expansion
    group by dia_semana, hora_inicio
    having count(*) > 1
  ) then
    return jsonb_build_object('ok', false, 'code', 'DUPLICATE_MONTHLY_SLOT', 'error', 'Há mais de uma escolha para o mesmo dia e horário.');
  end if;

  select count(*) into v_count from monthly_choice_expansion;
  v_required := v_plan.weekly_days * 2;
  if v_count <> v_required then
    return jsonb_build_object(
      'ok', false,
      'code', 'MONTHLY_QUOTA_MISMATCH',
      'error', format('Selecione exatamente %s encontros.', v_required),
      'required_slots', v_required,
      'selected_slots', v_count
    );
  end if;

  select count(distinct dia_semana)
  into v_day_count
  from monthly_choice_expansion;
  if v_day_count <> v_plan.weekly_days
     or exists (
       select 1
       from monthly_choice_expansion
       group by dia_semana
       having count(*) <> 2
     ) then
    return jsonb_build_object(
      'ok', false,
      'code', 'MONTHLY_DAYS_MISMATCH',
      'error', format('Distribua os encontros em %s dias, com 2 horários por dia.', v_plan.weekly_days)
    );
  end if;

  select count(*)
  into v_invalid_group_count
  from (
    select e.oficina_modular_id
    from monthly_choice_expansion e
    join public.oficina_modular o on o.id = e.oficina_modular_id
    where not e.orientadora
      and o.monthly_selection_mode = 'ALL_MEETINGS'
    group by e.oficina_modular_id
    having count(*) <> (
      select count(*)
      from public.oficina_modular_horarios h
      where h.oficina_modular_id = e.oficina_modular_id
    )
  ) invalid_groups;
  if v_invalid_group_count > 0 then
    return jsonb_build_object('ok', false, 'code', 'WORKSHOP_MEETINGS_INCOMPLETE', 'error', 'Selecione todos os encontros da oficina escolhida.');
  end if;

  insert into public.monthly_workshop_submissions (
    student_id,
    guardian_id,
    reference_month,
    weekly_days_snapshot,
    required_slots
  ) values (
    p_student_id,
    p_guardian_id,
    p_reference_month,
    v_plan.weekly_days,
    v_required
  )
  returning id into v_submission_id;

  insert into public.monthly_workshop_slots (
    submission_id,
    oficina_modular_id,
    horario_id,
    orientadora,
    dia_semana,
    hora_inicio,
    hora_fim
  )
  select
    v_submission_id,
    oficina_modular_id,
    horario_id,
    orientadora,
    dia_semana,
    hora_inicio,
    hora_fim
  from monthly_choice_expansion;

  insert into public.monthly_workshop_entries (
    submission_id,
    slot_id,
    student_id,
    guardian_id,
    entry_date,
    access_code
  )
  select
    v_submission_id,
    s.id,
    p_student_id,
    p_guardian_id,
    d::date,
    upper(substr(encode(gen_random_bytes(8), 'hex'), 1, 10))
  from public.monthly_workshop_slots s
  cross join generate_series(p_reference_month, v_month_end, interval '1 day') d
  left join public.oficina_modular o on o.id = s.oficina_modular_id
  where s.submission_id = v_submission_id
    and extract(isodow from d)::smallint = s.dia_semana
    and (
      s.orientadora
      or (
        d::date >= coalesce(o.data_inicio_validade, p_reference_month)
        and d::date <= coalesce(o.data_fim_validade, v_month_end)
      )
    );

  return jsonb_build_object(
    'ok', true,
    'idempotent', false,
    'submission_id', v_submission_id,
    'required_slots', v_required,
    'entry_count', (
      select count(*)
      from public.monthly_workshop_entries
      where submission_id = v_submission_id
    )
  );
end;
$$;

create or replace function public.unlock_monthly_workshops(
  p_submission_id uuid,
  p_admin_user_id uuid
)
returns jsonb
language plpgsql
set search_path = public
as $$
declare
  v_admin public.admin_users%rowtype;
  v_submission public.monthly_workshop_submissions%rowtype;
begin
  select *
  into v_admin
  from public.admin_users
  where id = p_admin_user_id
    and active = true
    and role in ('admin_principal', 'secretaria');
  if not found then
    return jsonb_build_object('ok', false, 'code', 'ADMIN_NOT_AUTHORIZED', 'error', 'Administrador não autorizado.');
  end if;

  select *
  into v_submission
  from public.monthly_workshop_submissions
  where id = p_submission_id
  for update;
  if not found then
    return jsonb_build_object('ok', false, 'code', 'SUBMISSION_NOT_FOUND', 'error', 'Confirmação mensal não encontrada.');
  end if;

  if v_submission.status = 'UNLOCKED' then
    return jsonb_build_object('ok', true, 'idempotent', true);
  end if;

  update public.monthly_workshop_submissions
  set status = 'UNLOCKED',
      unlocked_at = now(),
      unlocked_by = p_admin_user_id
  where id = p_submission_id;

  update public.monthly_workshop_entries
  set status = 'CANCELED',
      canceled_at = now()
  where submission_id = p_submission_id
    and status = 'CONFIRMED_BY_PLAN';

  insert into public.admin_audit_log (
    admin_user_id,
    username,
    role,
    action,
    entity_type,
    entity_id,
    details
  ) values (
    v_admin.id,
    v_admin.username,
    v_admin.role,
    'monthly_workshops.unlock',
    'monthly_workshop_submission',
    p_submission_id::text,
    jsonb_build_object(
      'student_id', v_submission.student_id,
      'reference_month', v_submission.reference_month
    )
  );

  return jsonb_build_object('ok', true, 'idempotent', false);
end;
$$;

alter table public.monthly_student_plans enable row level security;
alter table public.monthly_workshop_submissions enable row level security;
alter table public.monthly_workshop_slots enable row level security;
alter table public.monthly_workshop_entries enable row level security;

revoke all on table public.monthly_student_plans from anon, authenticated;
revoke all on table public.monthly_workshop_submissions from anon, authenticated;
revoke all on table public.monthly_workshop_slots from anon, authenticated;
revoke all on table public.monthly_workshop_entries from anon, authenticated;

revoke execute on function public.confirm_monthly_workshops(uuid, uuid, date, jsonb) from public, anon, authenticated;
revoke execute on function public.unlock_monthly_workshops(uuid, uuid) from public, anon, authenticated;
grant execute on function public.confirm_monthly_workshops(uuid, uuid, date, jsonb) to service_role;
grant execute on function public.unlock_monthly_workshops(uuid, uuid) to service_role;
