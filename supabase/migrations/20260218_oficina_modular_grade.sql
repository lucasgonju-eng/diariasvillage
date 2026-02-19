-- Módulo: Grade de Oficina Modular
-- Observação: este script assume que a tabela diaria(id) já existe no ambiente.

create extension if not exists "pgcrypto";

do $$
begin
  if not exists (select 1 from pg_type where typname = 'oficina_modular_status_quorum') then
    create type oficina_modular_status_quorum as enum ('LIVRE', 'EM_QUORUM', 'CONFIRMADA', 'CANCELADA');
  end if;
end
$$;

do $$
begin
  if not exists (select 1 from pg_type where typname = 'oficina_modular_tipo') then
    create type oficina_modular_tipo as enum ('RECORRENTE', 'OCASIONAL_30D', 'FIXA');
  end if;
end
$$;

do $$
begin
  if not exists (select 1 from pg_type where typname = 'diaria_oficina_modular_status') then
    create type diaria_oficina_modular_status as enum ('RASCUNHO', 'CONFIRMADA', 'CANCELADA');
  end if;
end
$$;

create table if not exists oficina_modular (
  id uuid primary key default gen_random_uuid(),
  nome varchar(150) not null,
  codigo varchar(20) not null,
  descricao text,
  ativa boolean not null default true,
  capacidade integer not null default 0,
  quorum_minimo integer,
  status_quorum oficina_modular_status_quorum not null default 'LIVRE',
  tipo oficina_modular_tipo not null default 'RECORRENTE',
  data_inicio_validade date,
  data_fim_validade date,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint chk_oficina_modular_capacidade_nonnegative check (capacidade >= 0),
  constraint chk_oficina_modular_quorum_nonnegative check (quorum_minimo is null or quorum_minimo >= 0),
  constraint chk_oficina_modular_validade check (
    data_inicio_validade is null
    or data_fim_validade is null
    or data_fim_validade >= data_inicio_validade
  )
);

create unique index if not exists uq_oficina_modular_codigo on oficina_modular (codigo);
create index if not exists idx_oficina_modular_ativa on oficina_modular (ativa);
create index if not exists idx_oficina_modular_status_quorum on oficina_modular (status_quorum);
create index if not exists idx_oficina_modular_tipo on oficina_modular (tipo);

create table if not exists oficina_modular_horarios (
  id uuid primary key default gen_random_uuid(),
  oficina_modular_id uuid not null references oficina_modular(id) on delete cascade,
  dia_semana smallint not null,
  hora_inicio time not null,
  hora_fim time not null,
  created_at timestamptz not null default now(),
  constraint chk_oficina_modular_horarios_dia_semana check (dia_semana between 1 and 7),
  constraint chk_oficina_modular_horarios_intervalo check (hora_inicio < hora_fim)
);

create index if not exists idx_oficina_modular_horarios_oficina_dia
  on oficina_modular_horarios (oficina_modular_id, dia_semana);

create table if not exists grade_slots (
  slot_id varchar(30) primary key,
  dia_semana smallint not null,
  hora_inicio time not null,
  hora_fim time not null,
  constraint chk_grade_slots_dia_semana check (dia_semana between 1 and 7),
  constraint chk_grade_slots_intervalo check (hora_inicio < hora_fim)
);

create index if not exists idx_grade_slots_dia_semana on grade_slots (dia_semana);

create table if not exists diaria_oficina_modular_reserva (
  id uuid primary key default gen_random_uuid(),
  diaria_id uuid not null references diaria(id) on delete cascade,
  oficina_modular_id uuid not null references oficina_modular(id) on delete restrict,
  dia_semana smallint not null,
  status diaria_oficina_modular_status not null default 'RASCUNHO',
  possui_segundo_encontro boolean not null default false,
  segundo_dia_semana smallint,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint chk_diaria_oficina_modular_reserva_dia_semana check (dia_semana between 1 and 7),
  constraint chk_diaria_oficina_modular_reserva_segundo_dia_semana check (
    segundo_dia_semana is null or segundo_dia_semana between 1 and 7
  ),
  constraint chk_diaria_oficina_modular_reserva_segundo_dia_coerencia check (
    (possui_segundo_encontro = false and segundo_dia_semana is null)
    or (possui_segundo_encontro = true and segundo_dia_semana is not null and segundo_dia_semana <> dia_semana)
  )
);

create index if not exists idx_diaria_oficina_modular_reserva_diaria_oficina
  on diaria_oficina_modular_reserva (diaria_id, oficina_modular_id);

create table if not exists diaria_slots_travados (
  id uuid primary key default gen_random_uuid(),
  diaria_id uuid not null references diaria(id) on delete cascade,
  slot_id varchar(30) not null references grade_slots(slot_id) on delete restrict,
  oficina_modular_id uuid not null references oficina_modular(id) on delete restrict,
  created_at timestamptz not null default now(),
  constraint uq_diaria_slots_travados_diaria_slot unique (diaria_id, slot_id)
);

comment on table diaria_slots_travados is
'Apenas slots do dia da diaria podem ser inseridos nesta tabela. O travamento e exclusivo do dia da diaria.';

comment on table diaria_oficina_modular_reserva is
'O segundo encontro da Oficina Modular deve ser identificado, mas nunca travado automaticamente.';

comment on column diaria_oficina_modular_reserva.possui_segundo_encontro is
'Preencher automaticamente com true quando existir horario da mesma oficina em dia_semana diferente do dia da diaria.';

comment on column diaria_oficina_modular_reserva.segundo_dia_semana is
'Armazena o outro dia da semana da oficina para uso de upsell; nao gera travamento automatico.';
