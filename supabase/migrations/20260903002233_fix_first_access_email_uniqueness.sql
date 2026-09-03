drop function if exists public.complete_first_access_claim(uuid, uuid, text, text);

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
  v_related_updated integer;
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

  update public.guardians
  set auth_user_id = p_auth_user_id,
      first_access_completed_at = now(),
      activation_claim_id = null,
      activation_claimed_at = null
  where activation_claim_id = p_claim_id
    and first_access_completed_at is null;

  get diagnostics v_related_updated = row_count;
  return jsonb_build_object(
    'ok', true,
    'primary_guardian_updated', v_primary_updated,
    'related_guardians_updated', v_related_updated
  );
end;
$$;

revoke execute on function public.complete_first_access_claim(uuid, uuid, uuid, text, text)
  from public, anon, authenticated;
grant execute on function public.complete_first_access_claim(uuid, uuid, uuid, text, text)
  to service_role;
