alter table public.admin_users
  add column if not exists requires_password_setup boolean not null default false;

-- Qualquer linha que tenha sido criada pelo código legado antes de o ALTER
-- adquirir o lock é tratada como não confiável e exige rotação pelo painel.
update public.admin_users
set requires_password_setup = true,
    updated_at = now()
where username = 'secretaria';

alter table public.admin_users
  drop constraint if exists chk_admin_users_password_setup_role;

alter table public.admin_users
  add constraint chk_admin_users_password_setup_role
  check (
    requires_password_setup = false
    or (username = 'secretaria' and role = 'secretaria')
  );

create or replace function public.enforce_secretaria_password_setup_origin()
returns trigger
language plpgsql
set search_path = ''
as $$
begin
  if new.username = 'secretaria'
    and coalesce(
      current_setting('app.secretaria_secure_setup', true),
      ''
    ) <> 'confirmed'
  then
    new.requires_password_setup := true;
  end if;
  return new;
end;
$$;

drop trigger if exists trg_secretaria_password_setup_origin
  on public.admin_users;
create trigger trg_secretaria_password_setup_origin
before insert on public.admin_users
for each row
execute function public.enforce_secretaria_password_setup_origin();

create or replace function public.claim_legacy_secretaria_bridge(
  p_password_hash text
)
returns jsonb
language plpgsql
security definer
set search_path = ''
as $$
declare
  v_user public.admin_users%rowtype;
begin
  if p_password_hash is null
    or char_length(p_password_hash) < 50
    or char_length(p_password_hash) > 255
    or p_password_hash not like '$2%'
  then
    return jsonb_build_object(
      'ok', false,
      'code', 'INVALID_PASSWORD_HASH',
      'error', 'Hash de senha inválido.'
    );
  end if;

  perform pg_advisory_xact_lock(hashtextextended('admin_users:secretaria', 0));

  select *
  into v_user
  from public.admin_users
  where username = 'secretaria'
  limit 1;

  if found then
    return jsonb_build_object(
      'ok', false,
      'code', 'SECRETARIA_ALREADY_EXISTS',
      'error', 'A conta da secretaria já existe.'
    );
  end if;

  insert into public.admin_users (
    username,
    password_hash,
    role,
    active,
    session_version,
    requires_password_setup
  )
  values (
    'secretaria',
    p_password_hash,
    'secretaria',
    true,
    1,
    true
  )
  returning * into v_user;

  insert into public.admin_audit_log (
    admin_user_id,
    username,
    role,
    action,
    success,
    details
  )
  values (
    v_user.id,
    'secretaria',
    'secretaria',
    'legacy_secretaria_bridge_claimed',
    true,
    jsonb_build_object('requires_password_setup', true)
  );

  return jsonb_build_object(
    'ok', true,
    'user', jsonb_build_object(
      'id', v_user.id,
      'username', v_user.username,
      'role', v_user.role,
      'active', v_user.active,
      'session_version', v_user.session_version,
      'requires_password_setup', v_user.requires_password_setup
    )
  );
end;
$$;

create or replace function public.configure_secretaria_credentials(
  p_actor_id uuid,
  p_actor_session_version integer,
  p_password_hash text
)
returns jsonb
language plpgsql
security definer
set search_path = ''
as $$
declare
  v_actor public.admin_users%rowtype;
  v_user public.admin_users%rowtype;
  v_created boolean := false;
begin
  if p_password_hash is null
    or char_length(p_password_hash) < 50
    or char_length(p_password_hash) > 255
    or p_password_hash not like '$2%'
  then
    return jsonb_build_object(
      'ok', false,
      'code', 'INVALID_PASSWORD_HASH',
      'error', 'Hash de senha inválido.'
    );
  end if;

  select *
  into v_actor
  from public.admin_users
  where id = p_actor_id
    and active = true
    and role = 'admin_principal'
    and session_version = p_actor_session_version
  for update;

  if not found then
    return jsonb_build_object(
      'ok', false,
      'code', 'ADMIN_NOT_AUTHORIZED',
      'error', 'Administrador não autorizado.'
    );
  end if;

  perform pg_advisory_xact_lock(hashtextextended('admin_users:secretaria', 0));

  select *
  into v_user
  from public.admin_users
  where username = 'secretaria'
  for update;

  if not found then
    v_created := true;
    perform set_config('app.secretaria_secure_setup', 'confirmed', true);
    insert into public.admin_users (
      username,
      password_hash,
      role,
      active,
      session_version,
      requires_password_setup
    )
    values (
      'secretaria',
      p_password_hash,
      'secretaria',
      true,
      1,
      false
    )
    returning * into v_user;
  else
    update public.admin_users
    set password_hash = p_password_hash,
        role = 'secretaria',
        active = true,
        session_version = greatest(session_version + 1, 1),
        requires_password_setup = false,
        updated_at = now()
    where id = v_user.id
    returning * into v_user;
  end if;

  insert into public.admin_audit_log (
    admin_user_id,
    username,
    role,
    action,
    success,
    details
  )
  values (
    v_actor.id,
    v_actor.username,
    v_actor.role,
    'configure_secretaria_access',
    true,
    jsonb_build_object('created', v_created)
  );

  return jsonb_build_object(
    'ok', true,
    'created', v_created,
    'user', jsonb_build_object(
      'id', v_user.id,
      'username', v_user.username,
      'role', v_user.role,
      'active', v_user.active,
      'session_version', v_user.session_version,
      'requires_password_setup', v_user.requires_password_setup
    )
  );
end;
$$;

revoke execute on function public.claim_legacy_secretaria_bridge(text)
  from public, anon, authenticated;
revoke execute on function public.configure_secretaria_credentials(uuid, integer, text)
  from public, anon, authenticated;
revoke execute on function public.enforce_secretaria_password_setup_origin()
  from public, anon, authenticated;

grant execute on function public.claim_legacy_secretaria_bridge(text)
  to service_role;
grant execute on function public.configure_secretaria_credentials(uuid, integer, text)
  to service_role;
