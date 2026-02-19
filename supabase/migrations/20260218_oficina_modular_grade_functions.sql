-- Funções transacionais de seleção/remoção da Grade de Oficina Modular.

create or replace function oficina_modular_grade_travar_e_reservar(
  p_diaria_id uuid,
  p_oficina_modular_id uuid,
  p_dia_semana smallint,
  p_slot_id varchar,
  p_possui_segundo_encontro boolean,
  p_segundo_dia_semana smallint
)
returns jsonb
language plpgsql
as $$
declare
  v_reserva_id uuid;
begin
  insert into diaria_slots_travados (diaria_id, slot_id, oficina_modular_id)
  values (p_diaria_id, p_slot_id, p_oficina_modular_id);

  select id
    into v_reserva_id
  from diaria_oficina_modular_reserva
  where diaria_id = p_diaria_id
    and oficina_modular_id = p_oficina_modular_id
  order by created_at asc
  limit 1;

  if v_reserva_id is null then
    insert into diaria_oficina_modular_reserva (
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
    update diaria_oficina_modular_reserva
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

create or replace function oficina_modular_grade_liberar_e_cancelar(
  p_diaria_id uuid,
  p_oficina_modular_id uuid,
  p_slot_id varchar default null,
  p_marcar_cancelada boolean default true
)
returns jsonb
language plpgsql
as $$
declare
  v_removed_count integer := 0;
begin
  if p_slot_id is not null then
    delete from diaria_slots_travados
     where diaria_id = p_diaria_id
       and slot_id = p_slot_id
       and oficina_modular_id = p_oficina_modular_id;

    get diagnostics v_removed_count = row_count;
  end if;

  if p_marcar_cancelada then
    update diaria_oficina_modular_reserva
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
