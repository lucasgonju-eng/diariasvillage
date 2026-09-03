alter table public.guardians
  add column if not exists account_session_version integer not null default 1;

do $$
begin
  if not exists (
    select 1
    from pg_constraint
    where conname = 'chk_guardians_account_session_version'
      and conrelid = 'public.guardians'::regclass
  ) then
    alter table public.guardians
      add constraint chk_guardians_account_session_version
      check (account_session_version > 0);
  end if;
end
$$;

create or replace function public.enforce_guardian_account_session_version()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
declare
  v_min_version integer;
  v_max_version integer;
begin
  if new.auth_user_id is null then
    return new;
  end if;

  perform pg_advisory_xact_lock(
    hashtextextended('guardian-session:' || new.auth_user_id::text, 0)
  );

  select min(g.account_session_version), max(g.account_session_version)
  into v_min_version, v_max_version
  from public.guardians g
  where g.auth_user_id = new.auth_user_id
    and g.id <> new.id;

  if v_min_version is not null and v_min_version <> v_max_version then
    raise exception 'GUARDIAN_SESSION_VERSION_CONFLICT';
  end if;
  if v_min_version is not null then
    new.account_session_version := v_min_version;
  end if;

  return new;
end;
$$;

drop trigger if exists trg_guardians_account_session_version on public.guardians;
create trigger trg_guardians_account_session_version
before insert or update of auth_user_id on public.guardians
for each row execute function public.enforce_guardian_account_session_version();

create table if not exists public.login_rate_limits (
  key_hash text primary key,
  bucket text not null,
  attempt_count integer not null default 0,
  window_started_at timestamptz not null default now(),
  blocked_until timestamptz,
  last_attempt_at timestamptz not null default now(),
  constraint chk_login_rate_limits_key_hash
    check (key_hash ~ '^[a-f0-9]{64}$'),
  constraint chk_login_rate_limits_bucket
    check (
      bucket in (
        'guardian_ip',
        'guardian_account',
        'guardian_combo',
        'admin_ip',
        'admin_account',
        'admin_combo'
      )
    ),
  constraint chk_login_rate_limits_attempt_count
    check (attempt_count >= 0)
);

create index if not exists idx_login_rate_limits_last_attempt
  on public.login_rate_limits (last_attempt_at);

alter table public.login_rate_limits enable row level security;
revoke all on table public.login_rate_limits from anon, authenticated;
grant select on table public.login_rate_limits to service_role;

create or replace function public.rotate_guardian_account_session(
  p_guardian_id uuid,
  p_auth_user_id uuid,
  p_expected_version integer
)
returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
  v_count integer;
  v_min_version integer;
  v_max_version integer;
  v_updated integer;
begin
  if p_guardian_id is null or coalesce(p_expected_version, 0) < 1 then
    return jsonb_build_object('ok', false, 'code', 'INVALID_SESSION_ROTATION');
  end if;

  if p_auth_user_id is not null then
    perform pg_advisory_xact_lock(
      hashtextextended('guardian-session:' || p_auth_user_id::text, 0)
    );
    perform 1
    from public.guardians
    where auth_user_id = p_auth_user_id
    order by id
    for update;

    if not exists (
      select 1
      from public.guardians
      where id = p_guardian_id
        and auth_user_id = p_auth_user_id
    ) then
      return jsonb_build_object('ok', false, 'code', 'GUARDIAN_ACCOUNT_CHANGED');
    end if;

    select count(*), min(account_session_version), max(account_session_version)
    into v_count, v_min_version, v_max_version
    from public.guardians
    where auth_user_id = p_auth_user_id;

    if v_count < 1
      or v_min_version <> v_max_version
      or v_min_version <> p_expected_version then
      return jsonb_build_object('ok', false, 'code', 'SESSION_VERSION_CONFLICT');
    end if;

    update public.guardians
    set account_session_version = p_expected_version + 1
    where auth_user_id = p_auth_user_id
      and account_session_version = p_expected_version;
    get diagnostics v_updated = row_count;
  else
    perform pg_advisory_xact_lock(
      hashtextextended('guardian-session:' || p_guardian_id::text, 0)
    );
    update public.guardians
    set account_session_version = p_expected_version + 1
    where id = p_guardian_id
      and auth_user_id is null
      and account_session_version = p_expected_version;
    get diagnostics v_updated = row_count;
    v_count := 1;
  end if;

  if v_updated <> v_count then
    raise exception 'SESSION_ROTATION_CONCURRENT_CHANGE';
  end if;

  return jsonb_build_object(
    'ok', true,
    'session_version', p_expected_version + 1,
    'updated_guardians', v_updated
  );
end;
$$;

create or replace function public.claim_login_attempt(
  p_key_hashes text[],
  p_buckets text[]
)
returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
  v_now timestamptz := clock_timestamp();
  v_index integer;
  v_key text;
  v_bucket text;
  v_threshold integer;
  v_row public.login_rate_limits%rowtype;
  v_retry_after integer := 0;
  v_key_hashes text[] := array[]::text[];
  v_bucket_names text[] := array[]::text[];
begin
  if coalesce(cardinality(p_key_hashes), 0) <> 3
    or cardinality(p_key_hashes) <> cardinality(p_buckets) then
    return jsonb_build_object('ok', false, 'code', 'INVALID_RATE_LIMIT_KEYS');
  end if;

  for v_index in 1..3
  loop
    v_key := lower(trim(p_key_hashes[v_index]));
    v_bucket := trim(p_buckets[v_index]);
    if v_key !~ '^[a-f0-9]{64}$'
      or v_bucket not in (
        'guardian_ip',
        'guardian_account',
        'guardian_combo',
        'admin_ip',
        'admin_account',
        'admin_combo'
      ) then
      return jsonb_build_object('ok', false, 'code', 'INVALID_RATE_LIMIT_KEY');
    end if;
    v_key_hashes := array_append(v_key_hashes, v_key);
    v_bucket_names := array_append(v_bucket_names, v_bucket);
  end loop;
  if not (
    v_bucket_names = array['guardian_ip', 'guardian_account', 'guardian_combo']::text[]
    or v_bucket_names = array['admin_ip', 'admin_account', 'admin_combo']::text[]
  ) then
    return jsonb_build_object('ok', false, 'code', 'INVALID_RATE_LIMIT_BUCKETS');
  end if;

  delete from public.login_rate_limits
  where key_hash in (
    select key_hash
    from public.login_rate_limits
    where last_attempt_at < v_now - interval '30 days'
    order by last_attempt_at
    limit 100
    for update skip locked
  );

  foreach v_key in array (
    select array_agg(distinct item order by item)
    from unnest(v_key_hashes) as item
  )
  loop
    perform pg_advisory_xact_lock(hashtextextended('login-rate:' || v_key, 0));
  end loop;

  insert into public.login_rate_limits (key_hash, bucket)
  values (v_key_hashes[1], v_bucket_names[1])
  on conflict (key_hash) do nothing;

  select *
  into v_row
  from public.login_rate_limits
  where key_hash = v_key_hashes[1]
  for update;

  if v_row.bucket <> v_bucket_names[1] then
    return jsonb_build_object('ok', false, 'code', 'RATE_LIMIT_BUCKET_CONFLICT');
  end if;
  if v_row.blocked_until is not null and v_row.blocked_until > v_now then
    return jsonb_build_object(
      'ok', true,
      'allowed', false,
      'retry_after', ceil(extract(epoch from (v_row.blocked_until - v_now)))::integer
    );
  end if;
  if v_row.window_started_at < v_now - interval '15 minutes' then
    update public.login_rate_limits
    set attempt_count = 0,
        window_started_at = v_now,
        blocked_until = null,
        last_attempt_at = v_now
    where key_hash = v_key_hashes[1];
  end if;

  for v_index in 2..3
  loop
    insert into public.login_rate_limits (key_hash, bucket)
    values (v_key_hashes[v_index], v_bucket_names[v_index])
    on conflict (key_hash) do nothing;
  end loop;

  for v_index in 1..3
  loop
    select *
    into v_row
    from public.login_rate_limits
    where key_hash = v_key_hashes[v_index]
    for update;
    if v_row.bucket <> v_bucket_names[v_index] then
      return jsonb_build_object('ok', false, 'code', 'RATE_LIMIT_BUCKET_CONFLICT');
    end if;
    if v_row.blocked_until is not null and v_row.blocked_until > v_now then
      v_retry_after := greatest(
        v_retry_after,
        ceil(extract(epoch from (v_row.blocked_until - v_now)))::integer
      );
    elsif v_row.window_started_at < v_now - interval '15 minutes' then
      update public.login_rate_limits
      set attempt_count = 0,
          window_started_at = v_now,
          blocked_until = null,
          last_attempt_at = v_now
      where key_hash = v_key_hashes[v_index]
      returning * into v_row;
    end if;
  end loop;

  if v_retry_after > 0 then
    return jsonb_build_object(
      'ok', true,
      'allowed', false,
      'retry_after', v_retry_after
    );
  end if;

  for v_index in 1..3
  loop
    v_key := v_key_hashes[v_index];
    v_bucket := v_bucket_names[v_index];
    v_threshold := case v_bucket
      when 'guardian_ip' then 60
      when 'guardian_account' then 10
      when 'guardian_combo' then 6
      when 'admin_ip' then 30
      when 'admin_account' then 8
      when 'admin_combo' then 6
      else 1
    end;

    update public.login_rate_limits
    set attempt_count = attempt_count + 1,
        last_attempt_at = v_now,
        blocked_until = case
          when attempt_count + 1 >= v_threshold then v_now + interval '15 minutes'
          else null
        end
    where key_hash = v_key
    returning * into v_row;
    if v_row.blocked_until is not null then
      v_retry_after := greatest(v_retry_after, 900);
    end if;
  end loop;

  return jsonb_build_object(
    'ok', true,
    'allowed', v_retry_after = 0,
    'retry_after', v_retry_after
  );
end;
$$;

create or replace function public.clear_login_attempts(p_key_hashes text[])
returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
  v_keys text[] := array[]::text[];
  v_key text;
  v_row public.login_rate_limits%rowtype;
begin
  if coalesce(cardinality(p_key_hashes), 0) <> 3 then
    return jsonb_build_object('ok', false, 'code', 'INVALID_RATE_LIMIT_KEYS');
  end if;

  foreach v_key in array p_key_hashes
  loop
    v_key := lower(trim(v_key));
    if v_key !~ '^[a-f0-9]{64}$' then
      return jsonb_build_object('ok', false, 'code', 'INVALID_RATE_LIMIT_KEY');
    end if;
    v_keys := array_append(v_keys, v_key);
  end loop;

  foreach v_key in array (
    select array_agg(distinct item order by item)
    from unnest(v_keys) as item
  )
  loop
    perform pg_advisory_xact_lock(hashtextextended('login-rate:' || v_key, 0));
  end loop;

  foreach v_key in array v_keys
  loop
    select *
    into v_row
    from public.login_rate_limits
    where key_hash = v_key
    for update;
    if not found then
      continue;
    end if;
    if v_row.bucket in ('guardian_ip', 'admin_ip') then
      update public.login_rate_limits
      set attempt_count = greatest(0, attempt_count - 1),
          blocked_until = null,
          last_attempt_at = clock_timestamp()
      where key_hash = v_key;
    else
      delete from public.login_rate_limits where key_hash = v_key;
    end if;
  end loop;

  return jsonb_build_object('ok', true);
end;
$$;

revoke all on function public.enforce_guardian_account_session_version() from public, anon, authenticated;
revoke all on function public.rotate_guardian_account_session(uuid, uuid, integer) from public, anon, authenticated;
revoke all on function public.claim_login_attempt(text[], text[]) from public, anon, authenticated;
revoke all on function public.clear_login_attempts(text[]) from public, anon, authenticated;
grant execute on function public.rotate_guardian_account_session(uuid, uuid, integer) to service_role;
grant execute on function public.claim_login_attempt(text[], text[]) to service_role;
grant execute on function public.clear_login_attempts(text[]) to service_role;
