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
  v_threshold integer;
  v_next_count integer;
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

    v_threshold := case v_row.bucket
      when 'guardian_ip' then 60
      when 'guardian_account' then 10
      when 'guardian_combo' then 6
      when 'admin_ip' then 30
      when 'admin_account' then 8
      when 'admin_combo' then 6
      else 1
    end;
    v_next_count := greatest(0, v_row.attempt_count - 1);

    update public.login_rate_limits
    set attempt_count = v_next_count,
        blocked_until = case
          when v_next_count >= v_threshold then blocked_until
          else null
        end,
        last_attempt_at = clock_timestamp()
    where key_hash = v_key;
  end loop;

  return jsonb_build_object('ok', true);
end;
$$;

create or replace function public.purge_stale_login_rate_limits(p_limit integer default 1000)
returns integer
language plpgsql
security definer
set search_path = public
as $$
declare
  v_deleted integer;
begin
  with stale as (
    select key_hash
    from public.login_rate_limits
    where last_attempt_at < clock_timestamp() - interval '30 days'
    order by last_attempt_at
    limit greatest(1, least(coalesce(p_limit, 1000), 5000))
    for update skip locked
  )
  delete from public.login_rate_limits target
  using stale
  where target.key_hash = stale.key_hash;
  get diagnostics v_deleted = row_count;
  return v_deleted;
end;
$$;

revoke all on function public.claim_login_attempt(text[], text[]) from public, anon, authenticated;
revoke all on function public.clear_login_attempts(text[]) from public, anon, authenticated;
revoke all on function public.purge_stale_login_rate_limits(integer) from public, anon, authenticated;
grant execute on function public.claim_login_attempt(text[], text[]) to service_role;
grant execute on function public.clear_login_attempts(text[]) to service_role;
grant execute on function public.purge_stale_login_rate_limits(integer) to service_role;
