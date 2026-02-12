-- Adiciona campos para pendência de cadastro paga
alter table pendencia_de_cadastro add column if not exists payment_date date;
alter table pendencia_de_cadastro add column if not exists access_code text;
alter table pendencia_de_cadastro add column if not exists student_id uuid references students(id);
alter table pendencia_de_cadastro add column if not exists enrollment text;
