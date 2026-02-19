-- Fluxo de diaria com etapa obrigatoria de Grade de Oficina Modular.

create extension if not exists "pgcrypto";

create table if not exists diaria (
  id uuid primary key default gen_random_uuid(),
  guardian_id uuid not null references guardians(id) on delete cascade,
  student_id uuid not null references students(id) on delete restrict,
  data_diaria date not null,
  grade_oficina_modular_ok boolean not null default false,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create index if not exists idx_diaria_guardian_data on diaria (guardian_id, data_diaria);
create index if not exists idx_diaria_student_data on diaria (student_id, data_diaria);
create index if not exists idx_diaria_grade_ok on diaria (grade_oficina_modular_ok);
