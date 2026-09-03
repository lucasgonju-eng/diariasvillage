alter table public.guardians
  add column if not exists auth_user_id uuid references auth.users(id) on delete set null,
  add column if not exists first_access_completed_at timestamptz,
  add column if not exists activation_claim_id uuid,
  add column if not exists activation_claimed_at timestamptz;

create index if not exists idx_guardians_auth_user_id
  on public.guardians (auth_user_id)
  where auth_user_id is not null;

create index if not exists idx_guardians_activation_claim_id
  on public.guardians (activation_claim_id)
  where activation_claim_id is not null;

-- Somente contas que possuem usuário Auth correspondente são consideradas
-- previamente ativadas. verified_at isolado também é usado por cadastros
-- administrativos e não comprova que o primeiro acesso ocorreu.
with auth_email as (
  select lower(trim(email)) as email_norm, min(id::text)::uuid as user_id
  from auth.users
  where email is not null and trim(email) <> ''
  group by lower(trim(email))
  having count(*) = 1
)
update public.guardians g
set auth_user_id = a.user_id,
    first_access_completed_at = coalesce(g.first_access_completed_at, g.verified_at)
from auth_email a
where g.auth_user_id is null
  and g.verified_at is not null
  and lower(trim(g.email)) = a.email_norm;

create table if not exists public.admin_users (
  id uuid primary key default gen_random_uuid(),
  username text not null,
  password_hash text not null,
  role text not null,
  active boolean not null default true,
  session_version integer not null default 1,
  last_login_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint chk_admin_users_username
    check (username = lower(trim(username)) and username <> ''),
  constraint chk_admin_users_role
    check (role in ('admin_principal', 'secretaria')),
  constraint chk_admin_users_session_version
    check (session_version > 0)
);

create unique index if not exists uq_admin_users_username_lower
  on public.admin_users (lower(username));

create table if not exists public.admin_audit_log (
  id bigint generated always as identity primary key,
  admin_user_id uuid references public.admin_users(id) on delete set null,
  username text not null,
  role text not null,
  action text not null,
  entity_type text,
  entity_id text,
  success boolean not null default true,
  request_id uuid not null default gen_random_uuid(),
  details jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

create index if not exists idx_admin_audit_log_created_at
  on public.admin_audit_log (created_at desc);

create index if not exists idx_admin_audit_log_admin_user
  on public.admin_audit_log (admin_user_id, created_at desc);

create or replace function public.begin_first_access_claim(
  p_guardian_id uuid,
  p_student_id uuid,
  p_claim_id uuid
)
returns jsonb
language plpgsql
set search_path = public
as $$
declare
  v_guardian public.guardians%rowtype;
  v_document text;
  v_name text;
begin
  select *
  into v_guardian
  from public.guardians
  where id = p_guardian_id
    and student_id = p_student_id
  for update;
  if not found then
    return jsonb_build_object('ok', false, 'code', 'GUARDIAN_STUDENT_MISMATCH', 'error', 'Responsável não vinculado ao aluno.');
  end if;

  v_document := regexp_replace(coalesce(v_guardian.parent_document, ''), '\D', '', 'g');
  v_name := lower(trim(coalesce(v_guardian.parent_name, '')));
  if v_document = '' or v_name = '' then
    return jsonb_build_object('ok', false, 'code', 'IDENTITY_INCOMPLETE', 'error', 'Cadastro do responsável incompleto.');
  end if;

  perform 1
  from public.guardians
  where regexp_replace(coalesce(parent_document, ''), '\D', '', 'g') = v_document
  for update;

  if exists (
    select 1
    from public.guardians
    where regexp_replace(coalesce(parent_document, ''), '\D', '', 'g') = v_document
      and lower(trim(coalesce(parent_name, ''))) <> v_name
  ) then
    return jsonb_build_object('ok', false, 'code', 'IDENTITY_CONFLICT', 'error', 'CPF associado a identidades divergentes. Procure a secretaria.');
  end if;

  if exists (
    select 1
    from public.guardians
    where regexp_replace(coalesce(parent_document, ''), '\D', '', 'g') = v_document
      and first_access_completed_at is not null
  ) then
    return jsonb_build_object('ok', false, 'code', 'FIRST_ACCESS_ALREADY_COMPLETED', 'error', 'Primeiro acesso já realizado. Faça login com CPF e senha.');
  end if;

  if exists (
    select 1
    from public.guardians
    where regexp_replace(coalesce(parent_document, ''), '\D', '', 'g') = v_document
      and activation_claim_id is distinct from p_claim_id
      and activation_claimed_at > now() - interval '15 minutes'
  ) then
    return jsonb_build_object('ok', false, 'code', 'FIRST_ACCESS_IN_PROGRESS', 'error', 'Cadastro em andamento. Aguarde alguns minutos e tente novamente.');
  end if;

  update public.guardians
  set activation_claim_id = p_claim_id,
      activation_claimed_at = now()
  where regexp_replace(coalesce(parent_document, ''), '\D', '', 'g') = v_document
    and lower(trim(coalesce(parent_name, ''))) = v_name
    and first_access_completed_at is null;

  return jsonb_build_object(
    'ok', true,
    'claim_id', p_claim_id,
    'guardian_id', p_guardian_id,
    'student_id', p_student_id
  );
end;
$$;

create or replace function public.complete_first_access_claim(
  p_claim_id uuid,
  p_auth_user_id uuid,
  p_email text,
  p_password_hash text
)
returns jsonb
language plpgsql
set search_path = public
as $$
declare
  v_updated integer;
begin
  update public.guardians
  set email = lower(trim(p_email)),
      password_hash = p_password_hash,
      auth_user_id = p_auth_user_id,
      verified_at = now(),
      first_access_completed_at = now(),
      activation_claim_id = null,
      activation_claimed_at = null
  where activation_claim_id = p_claim_id
    and first_access_completed_at is null;

  get diagnostics v_updated = row_count;
  if v_updated = 0 then
    return jsonb_build_object('ok', false, 'code', 'CLAIM_NOT_FOUND', 'error', 'Reserva do primeiro acesso não encontrada.');
  end if;

  return jsonb_build_object('ok', true, 'updated_guardians', v_updated);
end;
$$;

create or replace function public.cancel_first_access_claim(p_claim_id uuid)
returns jsonb
language plpgsql
set search_path = public
as $$
declare
  v_updated integer;
begin
  update public.guardians
  set activation_claim_id = null,
      activation_claimed_at = null
  where activation_claim_id = p_claim_id
    and first_access_completed_at is null;

  get diagnostics v_updated = row_count;
  return jsonb_build_object('ok', true, 'updated_guardians', v_updated);
end;
$$;

alter table public.admin_users enable row level security;
alter table public.admin_audit_log enable row level security;

revoke all on table public.admin_users from anon, authenticated;
revoke all on table public.admin_audit_log from anon, authenticated;
revoke all on sequence public.admin_audit_log_id_seq from anon, authenticated;

revoke execute on function public.begin_first_access_claim(uuid, uuid, uuid) from public, anon, authenticated;
revoke execute on function public.complete_first_access_claim(uuid, uuid, text, text) from public, anon, authenticated;
revoke execute on function public.cancel_first_access_claim(uuid) from public, anon, authenticated;
grant execute on function public.begin_first_access_claim(uuid, uuid, uuid) to service_role;
grant execute on function public.complete_first_access_claim(uuid, uuid, text, text) to service_role;
grant execute on function public.cancel_first_access_claim(uuid) to service_role;
