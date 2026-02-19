-- Logs de conversao de upsell do segundo encontro.

create extension if not exists "pgcrypto";

create table if not exists oficina_modular_upsell_log (
  id uuid primary key default gen_random_uuid(),
  diaria_origem_id uuid references diaria(id) on delete set null,
  diaria_destino_id uuid not null references diaria(id) on delete cascade,
  oficina_modular_id uuid not null references oficina_modular(id) on delete cascade,
  segundo_dia_semana smallint not null check (segundo_dia_semana between 1 and 7),
  created_at timestamptz not null default now()
);

create index if not exists idx_oficina_modular_upsell_log_origem on oficina_modular_upsell_log(diaria_origem_id);
create index if not exists idx_oficina_modular_upsell_log_destino on oficina_modular_upsell_log(diaria_destino_id);
create index if not exists idx_oficina_modular_upsell_log_oficina on oficina_modular_upsell_log(oficina_modular_id);
