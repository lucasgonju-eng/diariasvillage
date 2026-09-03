alter table public.guardians
  drop constraint if exists guardians_email_key;
drop index if exists public.guardians_email_key;
drop index if exists public.uq_guardians_parent_document_digits;

create index if not exists idx_guardians_email_normalized
  on public.guardians (lower(trim(email)));

create unique index if not exists uq_guardians_auth_user_student
  on public.guardians (auth_user_id, student_id)
  where auth_user_id is not null;

create unique index if not exists uq_students_enrollment_normalized
  on public.students (lower(trim(enrollment)))
  where nullif(trim(enrollment), '') is not null;

create or replace function public.is_valid_cpf_cnpj_digits(p_document text)
returns boolean
language plpgsql
immutable
strict
set search_path = ''
as $$
declare
  v text := regexp_replace(p_document, '\D', '', 'g');
  v_sum integer;
  v_digit integer;
  v_index integer;
  v_weights integer[];
begin
  if v ~ '^([0-9])\1+$' then
    return false;
  end if;

  if length(v) = 11 then
    v_sum := 0;
    for v_index in 1..9 loop
      v_sum := v_sum + substr(v, v_index, 1)::integer * (11 - v_index);
    end loop;
    v_digit := 11 - (v_sum % 11);
    if v_digit >= 10 then v_digit := 0; end if;
    if v_digit <> substr(v, 10, 1)::integer then return false; end if;

    v_sum := 0;
    for v_index in 1..10 loop
      v_sum := v_sum + substr(v, v_index, 1)::integer * (12 - v_index);
    end loop;
    v_digit := 11 - (v_sum % 11);
    if v_digit >= 10 then v_digit := 0; end if;
    return v_digit = substr(v, 11, 1)::integer;
  end if;

  if length(v) = 14 then
    v_weights := array[5,4,3,2,9,8,7,6,5,4,3,2];
    v_sum := 0;
    for v_index in 1..12 loop
      v_sum := v_sum + substr(v, v_index, 1)::integer * v_weights[v_index];
    end loop;
    v_digit := 11 - (v_sum % 11);
    if v_digit >= 10 then v_digit := 0; end if;
    if v_digit <> substr(v, 13, 1)::integer then return false; end if;

    v_weights := array[6,5,4,3,2,9,8,7,6,5,4,3,2];
    v_sum := 0;
    for v_index in 1..13 loop
      v_sum := v_sum + substr(v, v_index, 1)::integer * v_weights[v_index];
    end loop;
    v_digit := 11 - (v_sum % 11);
    if v_digit >= 10 then v_digit := 0; end if;
    return v_digit = substr(v, 14, 1)::integer;
  end if;

  return false;
end;
$$;

revoke all on function public.is_valid_cpf_cnpj_digits(text)
  from public, anon, authenticated;
grant execute on function public.is_valid_cpf_cnpj_digits(text)
  to service_role;

create or replace function public.enforce_guardian_document_identity()
returns trigger
language plpgsql
set search_path = ''
as $$
declare
  v_document text;
  v_name text;
  v_email text;
begin
  v_document := regexp_replace(coalesce(new.parent_document, ''), '\D', '', 'g');
  if v_document = '' then
    return new;
  end if;
  if not public.is_valid_cpf_cnpj_digits(v_document) then
    raise exception 'GUARDIAN_DOCUMENT_INVALID';
  end if;

  v_name := lower(trim(coalesce(new.parent_name, '')));
  v_email := lower(trim(coalesce(new.email, '')));
  perform pg_catalog.pg_advisory_xact_lock(
    pg_catalog.hashtextextended('guardian-document:' || v_document, 0)
  );

  if exists (
    select 1
    from public.guardians g
    where regexp_replace(coalesce(g.parent_document, ''), '\D', '', 'g') = v_document
      and g.id is distinct from new.id
      and (
        lower(trim(coalesce(g.parent_name, ''))) <> v_name
        or lower(trim(coalesce(g.email, ''))) <> v_email
        or (
          g.auth_user_id is not null
          and new.auth_user_id is not null
          and g.auth_user_id <> new.auth_user_id
        )
      )
  ) then
    raise exception 'GUARDIAN_DOCUMENT_IDENTITY_CONFLICT';
  end if;

  new.parent_document := v_document;
  return new;
end;
$$;

drop trigger if exists trg_guardians_document_identity on public.guardians;
create trigger trg_guardians_document_identity
before insert or update of parent_document, parent_name, email, auth_user_id
on public.guardians
for each row execute function public.enforce_guardian_document_identity();

revoke all on function public.enforce_guardian_document_identity()
  from public, anon, authenticated;

create or replace function public.enforce_guardian_email_identity()
returns trigger
language plpgsql
set search_path = ''
as $$
declare
  v_email text;
  v_document text;
  v_name text;
begin
  v_email := lower(trim(coalesce(new.email, '')));
  v_document := regexp_replace(coalesce(new.parent_document, ''), '\D', '', 'g');
  v_name := lower(trim(coalesce(new.parent_name, '')));

  if v_email = '' then
    raise exception 'GUARDIAN_EMAIL_REQUIRED';
  end if;

  perform pg_catalog.pg_advisory_xact_lock(
    pg_catalog.hashtextextended('guardian-email:' || v_email, 0)
  );

  if exists (
    select 1
    from public.guardians g
    where lower(trim(g.email)) = v_email
      and g.id is distinct from new.id
      and (
        regexp_replace(coalesce(g.parent_document, ''), '\D', '', 'g') <> v_document
        or lower(trim(coalesce(g.parent_name, ''))) <> v_name
        or (
          g.auth_user_id is not null
          and new.auth_user_id is not null
          and g.auth_user_id <> new.auth_user_id
        )
      )
  ) then
    raise exception 'GUARDIAN_EMAIL_IDENTITY_CONFLICT';
  end if;

  return new;
end;
$$;

drop trigger if exists trg_guardians_email_identity on public.guardians;
create trigger trg_guardians_email_identity
before insert or update of email, parent_document, parent_name, auth_user_id
on public.guardians
for each row execute function public.enforce_guardian_email_identity();

revoke all on function public.enforce_guardian_email_identity()
  from public, anon, authenticated;

create table if not exists public.family_link_requests (
  id uuid primary key default gen_random_uuid(),
  requester_auth_user_id uuid not null,
  requester_guardian_id uuid references public.guardians(id) on delete set null,
  source_student_id uuid not null references public.students(id) on delete restrict,
  requested_enrollment text not null,
  target_student_id uuid references public.students(id) on delete restrict,
  status text not null default 'PENDING'
    check (status in ('PENDING', 'APPROVED', 'REJECTED', 'BLOCKED')),
  requested_at timestamptz not null default now(),
  reviewed_at timestamptz,
  reviewed_by uuid references public.admin_users(id) on delete set null,
  review_note text,
  linked_guardian_id uuid references public.guardians(id) on delete set null,
  check (target_student_id is null or source_student_id <> target_student_id),
  check (char_length(trim(requested_enrollment)) between 1 and 80)
);

create unique index if not exists uq_family_link_request_pending
  on public.family_link_requests (requester_auth_user_id, lower(trim(requested_enrollment)))
  where status = 'PENDING';

create index if not exists idx_family_link_requests_status_requested
  on public.family_link_requests (status, requested_at desc);

create or replace function public.enforce_family_link_request_insert()
returns trigger
language plpgsql
set search_path = ''
as $$
begin
  new.status := 'PENDING';
  new.target_student_id := null;
  new.reviewed_at := null;
  new.reviewed_by := null;
  new.review_note := null;
  new.linked_guardian_id := null;
  return new;
end;
$$;

drop trigger if exists trg_family_link_request_insert on public.family_link_requests;
create trigger trg_family_link_request_insert
before insert on public.family_link_requests
for each row execute function public.enforce_family_link_request_insert();

revoke all on function public.enforce_family_link_request_insert()
  from public, anon, authenticated;

alter table public.family_link_requests enable row level security;
revoke all on table public.family_link_requests from anon, authenticated;
revoke all on table public.family_link_requests from service_role;
grant select, insert on table public.family_link_requests to service_role;

create or replace function public.review_family_link_request(
  p_request_id uuid,
  p_admin_user_id uuid,
  p_decision text,
  p_note text,
  p_password_hash text
)
returns jsonb
language plpgsql
security definer
set search_path = ''
as $$
declare
  v_request public.family_link_requests%rowtype;
  v_source public.guardians%rowtype;
  v_target public.guardians%rowtype;
  v_target_student public.students%rowtype;
  v_admin_username text;
  v_admin_role text;
  v_document text;
  v_name text;
  v_email text;
  v_customer_id text;
  v_customer_count integer;
  v_target_student_count integer;
  v_target_match_count integer;
  v_linked_guardian_id uuid;
begin
  select username, role
  into v_admin_username, v_admin_role
  from public.admin_users
  where id = p_admin_user_id
    and active = true
    and role in ('admin_principal', 'secretaria');

  if not found then
    return jsonb_build_object('ok', false, 'code', 'ADMIN_REVIEW_NOT_ALLOWED');
  end if;

  select *
  into v_request
  from public.family_link_requests
  where id = p_request_id
  for update;

  if not found then
    return jsonb_build_object('ok', false, 'code', 'FAMILY_LINK_REQUEST_NOT_FOUND');
  end if;
  if v_request.status <> 'PENDING' then
    return jsonb_build_object('ok', false, 'code', 'FAMILY_LINK_REQUEST_ALREADY_REVIEWED');
  end if;

  if upper(trim(p_decision)) = 'REJECT' then
    update public.family_link_requests
    set status = 'REJECTED',
        reviewed_at = now(),
        reviewed_by = p_admin_user_id,
        review_note = nullif(trim(p_note), '')
    where id = p_request_id;

    insert into public.admin_audit_log (
      admin_user_id, username, role, action, entity_type, entity_id, success, details
    ) values (
      p_admin_user_id,
      v_admin_username,
      v_admin_role,
      'FAMILY_LINK_REQUEST_REJECTED',
      'family_link_request',
      p_request_id::text,
      true,
      jsonb_build_object(
        'source_student_id', v_request.source_student_id,
        'target_student_id', v_request.target_student_id
      )
    );

    return jsonb_build_object('ok', true, 'status', 'REJECTED');
  end if;

  if upper(trim(p_decision)) <> 'APPROVE' then
    return jsonb_build_object('ok', false, 'code', 'INVALID_FAMILY_LINK_DECISION');
  end if;
  if coalesce(p_password_hash, '') = '' then
    return jsonb_build_object('ok', false, 'code', 'PASSWORD_HASH_REQUIRED');
  end if;

  lock table public.guardians in share row exclusive mode;

  select *
  into v_source
  from public.guardians
  where id = v_request.requester_guardian_id
    and student_id = v_request.source_student_id
    and auth_user_id = v_request.requester_auth_user_id
  for update;

  if not found then
    return jsonb_build_object('ok', false, 'code', 'REQUESTER_ACCOUNT_LINK_CHANGED');
  end if;

  perform 1
  from public.guardians
  where auth_user_id = v_request.requester_auth_user_id
  for update;

  v_document := regexp_replace(coalesce(v_source.parent_document, ''), '\D', '', 'g');
  v_name := lower(trim(coalesce(v_source.parent_name, '')));
  v_email := lower(trim(coalesce(v_source.email, '')));

  if not public.is_valid_cpf_cnpj_digits(v_document) or v_name = '' or v_email = '' then
    return jsonb_build_object('ok', false, 'code', 'REQUESTER_IDENTITY_INCOMPLETE');
  end if;

  if exists (
    select 1
    from public.guardians g
    where g.auth_user_id = v_request.requester_auth_user_id
      and (
        regexp_replace(coalesce(g.parent_document, ''), '\D', '', 'g') <> v_document
        or lower(trim(coalesce(g.parent_name, ''))) <> v_name
        or lower(trim(coalesce(g.email, ''))) <> v_email
      )
  ) then
    return jsonb_build_object('ok', false, 'code', 'REQUESTER_ACCOUNT_IDENTITY_CONFLICT');
  end if;

  select count(distinct nullif(trim(asaas_customer_id), '')),
         max(nullif(trim(asaas_customer_id), ''))
  into v_customer_count, v_customer_id
  from public.guardians
  where auth_user_id = v_request.requester_auth_user_id;

  if v_customer_count > 1 then
    return jsonb_build_object('ok', false, 'code', 'REQUESTER_ASAAS_LINK_CONFLICT');
  end if;

  perform pg_catalog.pg_advisory_xact_lock(
    pg_catalog.hashtextextended(
      'family-target:' || lower(trim(v_request.requested_enrollment)),
      0
    )
  );

  select count(*)
  into v_target_student_count
  from public.students
  where lower(trim(enrollment)) = lower(trim(v_request.requested_enrollment));

  if v_target_student_count <> 1 then
    return jsonb_build_object('ok', false, 'code', 'TARGET_ENROLLMENT_NOT_UNIQUE');
  end if;

  select *
  into v_target_student
  from public.students
  where lower(trim(enrollment)) = lower(trim(v_request.requested_enrollment))
  for update;

  if not v_target_student.active then
    return jsonb_build_object('ok', false, 'code', 'TARGET_STUDENT_NOT_ACTIVE');
  end if;
  if v_target_student.id = v_request.source_student_id then
    return jsonb_build_object('ok', false, 'code', 'TARGET_STUDENT_ALREADY_SELECTED');
  end if;

  if exists (
    select 1
    from public.guardians
    where student_id = v_target_student.id
      and auth_user_id = v_request.requester_auth_user_id
      and regexp_replace(coalesce(parent_document, ''), '\D', '', 'g') <> v_document
  ) then
    return jsonb_build_object('ok', false, 'code', 'TARGET_ACCOUNT_DOCUMENT_CONFLICT');
  end if;

  select count(*)
  into v_target_match_count
  from public.guardians
  where student_id = v_target_student.id
    and regexp_replace(coalesce(parent_document, ''), '\D', '', 'g') = v_document;

  if v_target_match_count > 1 then
    return jsonb_build_object('ok', false, 'code', 'TARGET_GUARDIAN_AMBIGUOUS');
  end if;

  if v_target_match_count = 1 then
    select *
    into v_target
    from public.guardians
    where student_id = v_target_student.id
      and regexp_replace(coalesce(parent_document, ''), '\D', '', 'g') = v_document
    for update;

    if lower(trim(coalesce(v_target.parent_name, ''))) <> v_name then
      return jsonb_build_object('ok', false, 'code', 'TARGET_GUARDIAN_NAME_CONFLICT');
    end if;
    if lower(trim(coalesce(v_target.email, ''))) <> v_email then
      return jsonb_build_object('ok', false, 'code', 'TARGET_GUARDIAN_EMAIL_CONFLICT');
    end if;
    if v_target.auth_user_id is not null
      and v_target.auth_user_id <> v_request.requester_auth_user_id then
      return jsonb_build_object('ok', false, 'code', 'TARGET_GUARDIAN_AUTH_CONFLICT');
    end if;
    if nullif(trim(v_target.asaas_customer_id), '') is not null
      and v_customer_id is not null
      and nullif(trim(v_target.asaas_customer_id), '') <> v_customer_id then
      return jsonb_build_object('ok', false, 'code', 'TARGET_GUARDIAN_ASAAS_CONFLICT');
    end if;

    update public.guardians
    set email = v_email,
        password_hash = p_password_hash,
        parent_name = v_source.parent_name,
        parent_phone = v_source.parent_phone,
        parent_document = v_document,
        auth_user_id = v_request.requester_auth_user_id,
        verified_at = coalesce(verified_at, now()),
        first_access_completed_at = coalesce(first_access_completed_at, now())
    where id = v_target.id
    returning id into v_linked_guardian_id;
  else
    insert into public.guardians (
      student_id,
      email,
      password_hash,
      verified_at,
      parent_name,
      parent_phone,
      parent_document,
      auth_user_id,
      first_access_completed_at
    ) values (
      v_target_student.id,
      v_email,
      p_password_hash,
      now(),
      v_source.parent_name,
      v_source.parent_phone,
      v_document,
      v_request.requester_auth_user_id,
      now()
    )
    returning id into v_linked_guardian_id;
  end if;

  update public.family_link_requests
  set status = 'APPROVED',
      target_student_id = v_target_student.id,
      reviewed_at = now(),
      reviewed_by = p_admin_user_id,
      review_note = nullif(trim(p_note), ''),
      linked_guardian_id = v_linked_guardian_id
  where id = p_request_id;

  insert into public.admin_audit_log (
    admin_user_id, username, role, action, entity_type, entity_id, success, details
  ) values (
    p_admin_user_id,
    v_admin_username,
    v_admin_role,
    'FAMILY_LINK_REQUEST_APPROVED',
    'family_link_request',
    p_request_id::text,
    true,
    jsonb_build_object(
      'source_student_id', v_request.source_student_id,
      'target_student_id', v_target_student.id,
      'linked_guardian_id', v_linked_guardian_id
    )
  );

  return jsonb_build_object(
    'ok', true,
    'status', 'APPROVED',
    'linked_guardian_id', v_linked_guardian_id
  );
end;
$$;

revoke execute on function public.review_family_link_request(uuid, uuid, text, text, text)
  from public, anon, authenticated;
grant execute on function public.review_family_link_request(uuid, uuid, text, text, text)
  to service_role;

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
    return jsonb_build_object('ok', false, 'code', 'GUARDIAN_STUDENT_LINK_NOT_FOUND');
  end if;

  v_document := regexp_replace(coalesce(v_guardian.parent_document, ''), '\D', '', 'g');
  v_name := lower(trim(coalesce(v_guardian.parent_name, '')));
  if not public.is_valid_cpf_cnpj_digits(v_document) or v_name = '' then
    return jsonb_build_object('ok', false, 'code', 'IDENTITY_INCOMPLETE');
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
      and lower(trim(coalesce(parent_name, ''))) = v_name
      and first_access_completed_at is not null
  ) then
    return jsonb_build_object('ok', false, 'code', 'FIRST_ACCESS_ALREADY_COMPLETED', 'error', 'Primeiro acesso já realizado. Faça login e solicite o vínculo do outro filho.');
  end if;

  if exists (
    select 1
    from public.guardians
    where regexp_replace(coalesce(parent_document, ''), '\D', '', 'g') = v_document
      and lower(trim(coalesce(parent_name, ''))) = v_name
      and activation_claim_id is distinct from p_claim_id
      and activation_claimed_at > now() - interval '15 minutes'
  ) then
    return jsonb_build_object('ok', false, 'code', 'FIRST_ACCESS_IN_PROGRESS', 'error', 'Cadastro em andamento. Aguarde alguns minutos e tente novamente.');
  end if;

  update public.guardians
  set activation_claim_id = p_claim_id,
      activation_claimed_at = now()
  where id = p_guardian_id
    and student_id = p_student_id
    and first_access_completed_at is null;

  if not found then
    return jsonb_build_object('ok', false, 'code', 'FIRST_ACCESS_ALREADY_COMPLETED');
  end if;

  return jsonb_build_object(
    'ok', true,
    'claim_id', p_claim_id,
    'guardian_id', p_guardian_id,
    'student_id', p_student_id
  );
end;
$$;

revoke execute on function public.begin_first_access_claim(uuid, uuid, uuid)
  from public, anon, authenticated;
grant execute on function public.begin_first_access_claim(uuid, uuid, uuid)
  to service_role;

create or replace function public.complete_first_access_claim(
  p_claim_id uuid,
  p_primary_guardian_id uuid,
  p_auth_user_id uuid,
  p_email text,
  p_password_hash text
)
returns jsonb
language plpgsql
set search_path = public
as $$
declare
  v_primary_updated integer;
begin
  update public.guardians
  set email = lower(trim(p_email)),
      password_hash = p_password_hash,
      auth_user_id = p_auth_user_id,
      verified_at = now(),
      first_access_completed_at = now(),
      activation_claim_id = null,
      activation_claimed_at = null
  where id = p_primary_guardian_id
    and activation_claim_id = p_claim_id
    and first_access_completed_at is null;

  get diagnostics v_primary_updated = row_count;
  if v_primary_updated = 0 then
    return jsonb_build_object('ok', false, 'code', 'PRIMARY_CLAIM_NOT_FOUND', 'error', 'Vínculo principal do primeiro acesso não encontrado.');
  end if;

  return jsonb_build_object(
    'ok', true,
    'primary_guardian_updated', v_primary_updated,
    'related_guardians_updated', 0
  );
end;
$$;

revoke execute on function public.complete_first_access_claim(uuid, uuid, uuid, text, text)
  from public, anon, authenticated;
grant execute on function public.complete_first_access_claim(uuid, uuid, uuid, text, text)
  to service_role;
