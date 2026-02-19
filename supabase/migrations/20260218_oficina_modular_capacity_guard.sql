-- Blindagem de capacidade + revalidacao checkout + confirmacao no pagamento.

do $$
begin
  if to_regclass('public.diaria') is null then
    raise exception 'Dependência ausente: tabela "diaria" não existe. Execute antes a migration 20260218_diaria_grade_flow.sql.';
  end if;

  if to_regclass('public.oficina_modular') is null then
    raise exception 'Dependência ausente: tabela "oficina_modular" não existe. Execute antes a migration 20260218_oficina_modular_grade.sql.';
  end if;

  if to_regclass('public.diaria_oficina_modular_reserva') is null then
    raise exception 'Dependência ausente: tabela "diaria_oficina_modular_reserva" não existe. Execute antes a migration 20260218_oficina_modular_grade.sql.';
  end if;

  if to_regclass('public.diaria_slots_travados') is null then
    raise exception 'Dependência ausente: tabela "diaria_slots_travados" não existe. Execute antes a migration 20260218_oficina_modular_grade.sql.';
  end if;
end
$$;

alter table diaria
  add column if not exists status_pagamento text not null default 'PENDENTE';

alter table diaria
  add column if not exists grade_travada boolean not null default false;

do $$
begin
  if not exists (
    select 1
    from pg_constraint
    where conname = 'chk_diaria_status_pagamento'
  ) then
    alter table diaria
      add constraint chk_diaria_status_pagamento
      check (status_pagamento in ('PENDENTE', 'PAGO', 'CANCELADO'));
  end if;
end
$$;

create index if not exists idx_diaria_status_pagamento on diaria(status_pagamento);
create index if not exists idx_diaria_grade_travada on diaria(grade_travada);

alter table payments
  add column if not exists diaria_id uuid references diaria(id);

create index if not exists idx_payments_diaria_id on payments(diaria_id);

alter table payments
  add column if not exists grade_alerta text;

create table if not exists oficina_modular_auditoria (
  id uuid primary key default gen_random_uuid(),
  diaria_id uuid not null references diaria(id) on delete cascade,
  oficina_modular_id uuid references oficina_modular(id) on delete set null,
  acao varchar(80) not null,
  payload_json jsonb,
  created_at timestamptz not null default now()
);

create index if not exists idx_oficina_modular_auditoria_diaria on oficina_modular_auditoria(diaria_id);
create index if not exists idx_oficina_modular_auditoria_acao on oficina_modular_auditoria(acao);

create or replace function oficina_modular_get_ocupacao(
  p_oficina_modular_id uuid,
  p_data_diaria date,
  p_dia_semana smallint
)
returns jsonb
language plpgsql
as $$
declare
  v_capacidade integer := 0;
  v_total_confirmadas integer := 0;
  v_vagas_restantes integer := 0;
begin
  select coalesce(capacidade, 0)
    into v_capacidade
  from oficina_modular
  where id = p_oficina_modular_id
  limit 1;

  if v_capacidade is null then
    v_capacidade := 0;
  end if;

  select count(*)
    into v_total_confirmadas
  from diaria_oficina_modular_reserva r
  join diaria d on d.id = r.diaria_id
  where r.oficina_modular_id = p_oficina_modular_id
    and r.dia_semana = p_dia_semana
    and r.status = 'CONFIRMADA'
    and d.status_pagamento = 'PAGO'
    and d.data_diaria = p_data_diaria;

  if v_capacidade <= 0 then
    v_vagas_restantes := 999999;
  else
    v_vagas_restantes := greatest(v_capacidade - v_total_confirmadas, 0);
  end if;

  return jsonb_build_object(
    'total_confirmadas', v_total_confirmadas,
    'capacidade', v_capacidade,
    'vagas_restantes', v_vagas_restantes
  );
end;
$$;

create or replace function oficina_modular_grade_revalidar_checkout(
  p_diaria_id uuid
)
returns jsonb
language plpgsql
as $$
declare
  v_diaria diaria%rowtype;
  v_reserva diaria_oficina_modular_reserva%rowtype;
  v_oficina oficina_modular%rowtype;
  v_slot_exists boolean;
  v_total_confirmadas integer;
  v_changed boolean := false;
  v_canceladas integer := 0;
  v_msg text := null;
begin
  select * into v_diaria
  from diaria
  where id = p_diaria_id
  for update;

  if not found then
    return jsonb_build_object('ok', false, 'error', 'Diária não encontrada.');
  end if;

  if v_diaria.status_pagamento = 'PAGO' or v_diaria.grade_travada then
    return jsonb_build_object('ok', false, 'error', 'Diária já está paga e travada.');
  end if;

  for v_reserva in
    select *
    from diaria_oficina_modular_reserva
    where diaria_id = p_diaria_id
      and status = 'RASCUNHO'
  loop
    select * into v_oficina
    from oficina_modular
    where id = v_reserva.oficina_modular_id;

    select exists(
      select 1
      from diaria_slots_travados dst
      where dst.diaria_id = p_diaria_id
        and dst.oficina_modular_id = v_reserva.oficina_modular_id
    ) into v_slot_exists;

    if not found or v_oficina.id is null or coalesce(v_oficina.ativa, false) = false or v_oficina.status_quorum = 'CANCELADA' or not v_slot_exists then
      update diaria_oficina_modular_reserva
         set status = 'CANCELADA',
             updated_at = now()
       where id = v_reserva.id;

      delete from diaria_slots_travados
       where diaria_id = p_diaria_id
         and oficina_modular_id = v_reserva.oficina_modular_id;

      insert into oficina_modular_auditoria(diaria_id, oficina_modular_id, acao, payload_json)
      values (
        p_diaria_id,
        v_reserva.oficina_modular_id,
        'REVALIDACAO_CHECKOUT',
        jsonb_build_object(
          'resultado', 'CANCELADA',
          'motivo', 'OFICINA_INATIVA_OU_SEM_SLOT'
        )
      );

      v_changed := true;
      v_canceladas := v_canceladas + 1;
      continue;
    end if;

    if coalesce(v_oficina.capacidade, 0) > 0 then
      select count(*)
        into v_total_confirmadas
      from diaria_oficina_modular_reserva r
      join diaria d on d.id = r.diaria_id
      where r.oficina_modular_id = v_reserva.oficina_modular_id
        and r.dia_semana = v_reserva.dia_semana
        and r.status = 'CONFIRMADA'
        and d.status_pagamento = 'PAGO'
        and d.data_diaria = v_diaria.data_diaria;

      if v_total_confirmadas >= v_oficina.capacidade then
        update diaria_oficina_modular_reserva
           set status = 'CANCELADA',
               updated_at = now()
         where id = v_reserva.id;

        delete from diaria_slots_travados
         where diaria_id = p_diaria_id
           and oficina_modular_id = v_reserva.oficina_modular_id;

        insert into oficina_modular_auditoria(diaria_id, oficina_modular_id, acao, payload_json)
        values (
          p_diaria_id,
          v_reserva.oficina_modular_id,
          'CANCELAMENTO_LOTACAO',
          jsonb_build_object(
            'origem', 'REVALIDACAO_CHECKOUT',
            'capacidade', v_oficina.capacidade,
            'total_confirmadas', v_total_confirmadas
          )
        );

        v_changed := true;
        v_canceladas := v_canceladas + 1;
      end if;
    end if;
  end loop;

  if v_changed then
    v_msg := 'Ops! Enquanto você montava a grade, essa Oficina Modular ficou sem vagas.' || E'\n' ||
             'Sem stress 😊 escolha outra opção para esse horário e siga com a diária.';

    return jsonb_build_object(
      'ok', false,
      'changed', true,
      'canceladas', v_canceladas,
      'message', v_msg
    );
  end if;

  update diaria
     set grade_oficina_modular_ok = true,
         updated_at = now()
   where id = p_diaria_id;

  insert into oficina_modular_auditoria(diaria_id, acao, payload_json)
  values (
    p_diaria_id,
    'REVALIDACAO_CHECKOUT',
    jsonb_build_object('resultado', 'OK')
  );

  return jsonb_build_object('ok', true, 'changed', false, 'canceladas', 0);
end;
$$;

create or replace function oficina_modular_grade_confirmar_pagamento(
  p_diaria_id uuid
)
returns jsonb
language plpgsql
as $$
declare
  v_diaria diaria%rowtype;
  v_reserva diaria_oficina_modular_reserva%rowtype;
  v_oficina oficina_modular%rowtype;
  v_slot_exists boolean;
  v_total_confirmadas integer;
  v_confirmadas integer := 0;
  v_canceladas integer := 0;
begin
  select * into v_diaria
  from diaria
  where id = p_diaria_id
  for update;

  if not found then
    return jsonb_build_object('ok', false, 'error', 'Diária não encontrada.');
  end if;

  if v_diaria.status_pagamento = 'PAGO' and coalesce(v_diaria.grade_travada, false) = true then
    return jsonb_build_object('ok', true, 'idempotent', true, 'confirmadas', 0, 'canceladas', 0);
  end if;

  update diaria
     set status_pagamento = 'PAGO',
         updated_at = now()
   where id = p_diaria_id;

  for v_reserva in
    select *
    from diaria_oficina_modular_reserva
    where diaria_id = p_diaria_id
      and status = 'RASCUNHO'
  loop
    select * into v_oficina
    from oficina_modular
    where id = v_reserva.oficina_modular_id;

    select exists(
      select 1
      from diaria_slots_travados dst
      where dst.diaria_id = p_diaria_id
        and dst.oficina_modular_id = v_reserva.oficina_modular_id
    ) into v_slot_exists;

    if not found or v_oficina.id is null or coalesce(v_oficina.ativa, false) = false or v_oficina.status_quorum = 'CANCELADA' or not v_slot_exists then
      update diaria_oficina_modular_reserva
         set status = 'CANCELADA',
             updated_at = now()
       where id = v_reserva.id;

      delete from diaria_slots_travados
       where diaria_id = p_diaria_id
         and oficina_modular_id = v_reserva.oficina_modular_id;

      insert into oficina_modular_auditoria(diaria_id, oficina_modular_id, acao, payload_json)
      values (
        p_diaria_id,
        v_reserva.oficina_modular_id,
        'CONFIRMACAO_PAGAMENTO',
        jsonb_build_object(
          'resultado', 'CANCELADA',
          'motivo', 'OFICINA_INATIVA_OU_SEM_SLOT'
        )
      );

      v_canceladas := v_canceladas + 1;
      continue;
    end if;

    if coalesce(v_oficina.capacidade, 0) > 0 then
      select count(*)
        into v_total_confirmadas
      from diaria_oficina_modular_reserva r
      join diaria d on d.id = r.diaria_id
      where r.oficina_modular_id = v_reserva.oficina_modular_id
        and r.dia_semana = v_reserva.dia_semana
        and r.status = 'CONFIRMADA'
        and d.status_pagamento = 'PAGO'
        and d.data_diaria = v_diaria.data_diaria
        and d.id <> p_diaria_id;

      if v_total_confirmadas >= v_oficina.capacidade then
        update diaria_oficina_modular_reserva
           set status = 'CANCELADA',
               updated_at = now()
         where id = v_reserva.id;

        delete from diaria_slots_travados
         where diaria_id = p_diaria_id
           and oficina_modular_id = v_reserva.oficina_modular_id;

        insert into oficina_modular_auditoria(diaria_id, oficina_modular_id, acao, payload_json)
        values (
          p_diaria_id,
          v_reserva.oficina_modular_id,
          'CANCELAMENTO_LOTACAO',
          jsonb_build_object(
            'origem', 'CONFIRMACAO_PAGAMENTO',
            'capacidade', v_oficina.capacidade,
            'total_confirmadas', v_total_confirmadas
          )
        );

        v_canceladas := v_canceladas + 1;
        continue;
      end if;
    end if;

    update diaria_oficina_modular_reserva
       set status = 'CONFIRMADA',
           updated_at = now()
     where id = v_reserva.id;

    insert into oficina_modular_auditoria(diaria_id, oficina_modular_id, acao, payload_json)
    values (
      p_diaria_id,
      v_reserva.oficina_modular_id,
      'CONFIRMACAO_PAGAMENTO',
      jsonb_build_object('resultado', 'CONFIRMADA')
    );

    v_confirmadas := v_confirmadas + 1;
  end loop;

  update diaria
     set grade_travada = true,
         grade_oficina_modular_ok = true,
         status_pagamento = 'PAGO',
         updated_at = now()
   where id = p_diaria_id;

  return jsonb_build_object(
    'ok', true,
    'idempotent', false,
    'confirmadas', v_confirmadas,
    'canceladas', v_canceladas
  );
end;
$$;
