-- Impede que um responsável selecione ou remova oficinas de uma diária alheia.
-- A autorização é repetida no banco, dentro da mesma transação da mutação.

drop function if exists public.oficina_modular_grade_travar_e_reservar(
  uuid,
  uuid,
  smallint,
  character varying,
  boolean,
  smallint
);

drop function if exists public.oficina_modular_grade_liberar_e_cancelar(
  uuid,
  uuid,
  character varying,
  boolean
);

create function public.oficina_modular_grade_travar_e_reservar(
  p_diaria_id uuid,
  p_guardian_id uuid,
  p_oficina_modular_id uuid,
  p_dia_semana smallint,
  p_slot_id character varying,
  p_possui_segundo_encontro boolean,
  p_segundo_dia_semana smallint
)
returns jsonb
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_reserva_id uuid;
begin
  perform 1
  from public.diaria
  where id = p_diaria_id
    and guardian_id = p_guardian_id
    and coalesce(status_pagamento, 'PENDENTE') = 'PENDENTE'
    and coalesce(grade_travada, false) = false
  for update;

  if not found then
    return jsonb_build_object(
      'ok', false,
      'reason', 'ACESSO_NEGADO_OU_ESTADO_INVALIDO'
    );
  end if;

  insert into public.diaria_slots_travados (diaria_id, slot_id, oficina_modular_id)
  values (p_diaria_id, p_slot_id, p_oficina_modular_id);

  select id
    into v_reserva_id
  from public.diaria_oficina_modular_reserva
  where diaria_id = p_diaria_id
    and oficina_modular_id = p_oficina_modular_id
  order by created_at asc
  limit 1;

  if v_reserva_id is null then
    insert into public.diaria_oficina_modular_reserva (
      diaria_id,
      oficina_modular_id,
      dia_semana,
      status,
      possui_segundo_encontro,
      segundo_dia_semana
    )
    values (
      p_diaria_id,
      p_oficina_modular_id,
      p_dia_semana,
      'RASCUNHO',
      p_possui_segundo_encontro,
      p_segundo_dia_semana
    );
  else
    update public.diaria_oficina_modular_reserva
       set dia_semana = p_dia_semana,
           status = 'RASCUNHO',
           possui_segundo_encontro = p_possui_segundo_encontro,
           segundo_dia_semana = p_segundo_dia_semana,
           updated_at = now()
     where id = v_reserva_id;
  end if;

  return jsonb_build_object(
    'ok', true,
    'slot_id', p_slot_id
  );
exception
  when unique_violation then
    return jsonb_build_object(
      'ok', false,
      'reason', 'CONFLITO_SLOT',
      'slot_id', p_slot_id
    );
end;
$$;

create function public.oficina_modular_grade_liberar_e_cancelar(
  p_diaria_id uuid,
  p_guardian_id uuid,
  p_oficina_modular_id uuid,
  p_slot_id character varying default null,
  p_marcar_cancelada boolean default true
)
returns jsonb
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_removed_count integer := 0;
begin
  perform 1
  from public.diaria
  where id = p_diaria_id
    and guardian_id = p_guardian_id
    and coalesce(status_pagamento, 'PENDENTE') = 'PENDENTE'
    and coalesce(grade_travada, false) = false
  for update;

  if not found then
    return jsonb_build_object(
      'ok', false,
      'reason', 'ACESSO_NEGADO_OU_ESTADO_INVALIDO'
    );
  end if;

  if p_slot_id is not null then
    delete from public.diaria_slots_travados
     where diaria_id = p_diaria_id
       and slot_id = p_slot_id
       and oficina_modular_id = p_oficina_modular_id;

    get diagnostics v_removed_count = row_count;
  end if;

  if p_marcar_cancelada then
    update public.diaria_oficina_modular_reserva
       set status = 'CANCELADA',
           updated_at = now()
     where diaria_id = p_diaria_id
       and oficina_modular_id = p_oficina_modular_id;
  end if;

  return jsonb_build_object(
    'ok', true,
    'slot_id', p_slot_id,
    'removed', v_removed_count > 0
  );
end;
$$;

revoke all privileges on function public.oficina_modular_grade_travar_e_reservar(
  uuid,
  uuid,
  uuid,
  smallint,
  character varying,
  boolean,
  smallint
) from public, anon, authenticated;

revoke all privileges on function public.oficina_modular_grade_liberar_e_cancelar(
  uuid,
  uuid,
  uuid,
  character varying,
  boolean
) from public, anon, authenticated;

grant execute on function public.oficina_modular_grade_travar_e_reservar(
  uuid,
  uuid,
  uuid,
  smallint,
  character varying,
  boolean,
  smallint
) to service_role;

grant execute on function public.oficina_modular_grade_liberar_e_cancelar(
  uuid,
  uuid,
  uuid,
  character varying,
  boolean
) to service_role;
