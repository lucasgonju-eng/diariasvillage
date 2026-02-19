-- Evita duplicidade de CPF/CNPJ em guardians, ignorando pontuacao.

create unique index if not exists uq_guardians_parent_document_digits
on guardians ((regexp_replace(parent_document, '\D', '', 'g')))
where parent_document is not null
  and regexp_replace(parent_document, '\D', '', 'g') <> '';
