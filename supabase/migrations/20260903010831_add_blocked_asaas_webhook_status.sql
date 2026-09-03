alter table public.asaas_webhook_events
  drop constraint if exists asaas_webhook_events_status_valid;

alter table public.asaas_webhook_events
  add constraint asaas_webhook_events_status_valid
  check (status in ('RECEIVED', 'PROCESSING', 'PROCESSED', 'IGNORED', 'BLOCKED', 'FAILED'));

create or replace function public.complete_asaas_webhook_event(
  p_event_id text,
  p_status text default 'PROCESSED'
)
returns jsonb
language plpgsql
set search_path = public
as $$
declare
  v_updated integer := 0;
begin
  if p_status not in ('PROCESSED', 'IGNORED', 'BLOCKED') then
    return jsonb_build_object('ok', false, 'code', 'INVALID_STATUS');
  end if;

  update public.asaas_webhook_events
  set status = p_status,
      processed_at = now(),
      locked_at = null,
      last_error = null
  where event_id = p_event_id
    and status = 'PROCESSING';
  get diagnostics v_updated = row_count;

  return jsonb_build_object('ok', v_updated = 1, 'updated', v_updated = 1);
end;
$$;

revoke execute on function public.complete_asaas_webhook_event(text, text)
  from public, anon, authenticated;
grant execute on function public.complete_asaas_webhook_event(text, text)
  to service_role;
