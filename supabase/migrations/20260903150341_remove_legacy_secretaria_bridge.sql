revoke execute on function public.claim_legacy_secretaria_bridge(text)
  from public, anon, authenticated, service_role;

drop function if exists public.claim_legacy_secretaria_bridge(text);
