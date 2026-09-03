-- Permite ativar somente um vínculo de uma família legada sem conceder acesso
-- automático aos demais filhos. E-mails sintéticos continuam sem valor de
-- identidade e só podem ser substituídos ao vincular uma conta Auth.

create or replace function public.is_guardian_placeholder_email(p_email text)
returns boolean
language sql
immutable
set search_path = ''
as $$
  select
    right(lower(trim(coalesce(p_email, ''))), length('@placeholder.local')) = '@placeholder.local'
    or right(lower(trim(coalesce(p_email, ''))), length('@diariasvillage.local')) = '@diariasvillage.local';
$$;

revoke all on function public.is_guardian_placeholder_email(text)
  from public, anon, authenticated;
grant execute on function public.is_guardian_placeholder_email(text)
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
        or (
          lower(trim(coalesce(g.email, ''))) <> v_email
          and not (
            new.auth_user_id is not null
            and new.first_access_completed_at is not null
            and not public.is_guardian_placeholder_email(new.email)
            and g.auth_user_id is null
            and g.first_access_completed_at is null
            and public.is_guardian_placeholder_email(g.email)
          )
        )
        or (
          g.auth_user_id is not null
          and new.auth_user_id is not null
          and g.auth_user_id <> new.auth_user_id
        )
        or (
          nullif(trim(g.asaas_customer_id), '') is not null
          and nullif(trim(new.asaas_customer_id), '') is not null
          and nullif(trim(g.asaas_customer_id), '') <> nullif(trim(new.asaas_customer_id), '')
        )
      )
  ) then
    raise exception 'GUARDIAN_DOCUMENT_IDENTITY_CONFLICT';
  end if;

  new.parent_document := v_document;
  return new;
end;
$$;

revoke all on function public.enforce_guardian_document_identity()
  from public, anon, authenticated;

drop trigger if exists trg_guardians_document_identity on public.guardians;
create trigger trg_guardians_document_identity
before insert or update of parent_document, parent_name, email, auth_user_id, asaas_customer_id
on public.guardians
for each row execute function public.enforce_guardian_document_identity();

drop function if exists public.review_family_link_request(uuid, uuid, text, text, text);

create function public.review_family_link_request(
  p_request_id uuid,
  p_admin_user_id uuid,
  p_decision text,
  p_note text,
  p_password_hash text,
  p_validated_asaas_customer_id text,
  p_validated_identity_fingerprint text
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
  v_validated_customer_id text;
  v_target_customer_id text;
  v_current_identity_fingerprint text;
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

  if not public.is_valid_cpf_cnpj_digits(v_document)
    or v_name = ''
    or v_email = ''
    or public.is_guardian_placeholder_email(v_email)
  then
    return jsonb_build_object('ok', false, 'code', 'REQUESTER_IDENTITY_INCOMPLETE');
  end if;

  v_current_identity_fingerprint := encode(
    extensions.digest(v_name || E'\n' || v_email || E'\n' || v_document, 'sha256'),
    'hex'
  );
  if coalesce(trim(p_validated_identity_fingerprint), '')
    <> v_current_identity_fingerprint then
    return jsonb_build_object(
      'ok', false, 'code', 'REQUESTER_IDENTITY_CHANGED_AFTER_VALIDATION'
    );
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
  v_validated_customer_id := nullif(trim(p_validated_asaas_customer_id), '');
  if v_customer_id is null and v_validated_customer_id is not null then
    return jsonb_build_object('ok', false, 'code', 'REQUESTER_ASAAS_VALIDATION_REQUIRED');
  end if;
  if v_customer_id is not null
    and v_validated_customer_id is distinct from v_customer_id then
    return jsonb_build_object('ok', false, 'code', 'REQUESTER_ASAAS_VALIDATION_REQUIRED');
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
    if v_target.auth_user_id is null
      and v_target.first_access_completed_at is not null then
      return jsonb_build_object('ok', false, 'code', 'TARGET_GUARDIAN_AUTH_CONFLICT');
    end if;
    if lower(trim(coalesce(v_target.email, ''))) <> v_email
      and not public.is_guardian_placeholder_email(v_target.email)
    then
      return jsonb_build_object('ok', false, 'code', 'TARGET_GUARDIAN_EMAIL_CONFLICT');
    end if;
    if v_target.auth_user_id is not null
      and v_target.auth_user_id <> v_request.requester_auth_user_id then
      return jsonb_build_object('ok', false, 'code', 'TARGET_GUARDIAN_AUTH_CONFLICT');
    end if;
    v_target_customer_id := nullif(trim(v_target.asaas_customer_id), '');
    if v_target_customer_id is not null
      and (
        v_validated_customer_id is null
        or v_target_customer_id <> v_validated_customer_id
      ) then
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

revoke execute on function public.review_family_link_request(uuid, uuid, text, text, text, text, text)
  from public, anon, authenticated;
grant execute on function public.review_family_link_request(uuid, uuid, text, text, text, text, text)
  to service_role;
