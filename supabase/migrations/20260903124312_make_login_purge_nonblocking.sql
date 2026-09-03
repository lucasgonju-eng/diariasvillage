create or replace function public.purge_stale_login_rate_limits(p_limit integer default 1000)
returns integer
language plpgsql
security definer
set search_path = public
as $$
declare
  v_key text;
  v_deleted integer := 0;
  v_row_count integer;
begin
  for v_key in
    select key_hash
    from public.login_rate_limits
    where last_attempt_at < clock_timestamp() - interval '30 days'
    order by last_attempt_at, key_hash
    limit greatest(1, least(coalesce(p_limit, 1000), 5000))
  loop
    if not pg_try_advisory_xact_lock(hashtextextended('login-rate:' || v_key, 0)) then
      continue;
    end if;
    delete from public.login_rate_limits
    where key_hash = v_key
      and last_attempt_at < clock_timestamp() - interval '30 days';
    get diagnostics v_row_count = row_count;
    v_deleted := v_deleted + v_row_count;
  end loop;
  return v_deleted;
end;
$$;

revoke all on function public.purge_stale_login_rate_limits(integer) from public, anon, authenticated;
grant execute on function public.purge_stale_login_rate_limits(integer) to service_role;
