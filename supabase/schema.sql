create extension if not exists "pgcrypto";

create table if not exists students (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  grade integer not null,
  active boolean not null default true,
  created_at timestamptz not null default now()
);

create table if not exists guardians (
  id uuid primary key default gen_random_uuid(),
  student_id uuid not null references students(id),
  email text not null unique,
  password_hash text not null,
  asaas_customer_id text,
  verified_at timestamptz,
  parent_name text,
  parent_phone text,
  parent_document text,
  created_at timestamptz not null default now()
);

create table if not exists verification_tokens (
  id uuid primary key default gen_random_uuid(),
  guardian_id uuid not null references guardians(id) on delete cascade,
  token text not null unique,
  expires_at timestamptz not null,
  created_at timestamptz not null default now()
);

create table if not exists payments (
  id uuid primary key default gen_random_uuid(),
  guardian_id uuid not null references guardians(id),
  student_id uuid not null references students(id),
  payment_date date not null,
  daily_type text not null,
  amount numeric(10,2) not null,
  status text not null default 'pending',
  billing_type text not null,
  asaas_payment_id text,
  access_code text,
  paid_at timestamptz,
  created_at timestamptz not null default now()
);

create index if not exists idx_students_name on students using gin (to_tsvector('simple', name));
create index if not exists idx_students_grade on students(grade);
create index if not exists idx_payments_guardian on payments(guardian_id);
