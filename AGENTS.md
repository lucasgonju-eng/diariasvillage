# Diárias Village — contexto permanente para agentes

## Estado da remediação de identidade e pagamentos

Em 01/09/2026 foi corrigido um incidente em que uma cobrança para o aluno Luis
Felipe Araujo Benfica, usando o CPF de Keila (00624611175), reutilizou no Asaas
um cliente pertencente a Fernando de Brito Clemene. A causa foi uma combinação
de seleção administrativa por nome, escolha implícita de responsável e mutação
de um cliente Asaas já vinculado sem validar toda a identidade.

As correções abaixo estão ativas em produção. Não reintroduza buscas ambíguas,
fallback por nome, associação por e-mail isolado ou atualização isolada de CPF.

## Regras obrigatórias de identidade

- Transporte `student_id` e `guardian_id` como UUIDs em fluxos administrativos.
- Sempre valide `guardians.student_id = students.id`.
- Se houver mais de um responsável, exija escolha explícita; nunca escolha o
  primeiro ou o mais recente silenciosamente.
- Nome, e-mail e CPF/CNPJ formam a identidade composta. CPF ou e-mail isolados
  não autorizam vincular cliente, cobrança ou responsável.
- Normalize CPF/CNPJ apenas para comparação e valide os dígitos verificadores.
- Nunca altere apenas `cpfCnpj` de um cliente Asaas existente.
- Nunca reutilize `asaas_customer_id` se a identidade remota divergir.
- Conflitos devem bloquear a operação e exigir revisão humana.

`src/AsaasCustomerIdentity.php` centraliza:

- validação de CPF/CNPJ;
- detecção de conflito local de documento;
- comparação exata da identidade local e remota;
- validação do cliente Asaas existente;
- sincronização segura de nome, e-mail, telefone e documento;
- criação de cliente novo quando o vínculo anterior não existe ou foi removido.

Todos os fluxos que criam ou enviam cobranças devem usar essa classe:

- `public/api/create-payment.php`;
- `public/api/admin-send-pending-charges.php`;
- `public/api/admin-send-pending-charges-v2.php`;
- `public/api/admin-resend-feb-charge.php`;
- `public/api/admin-attendance.php`;
- `public/api/financeiro-pay.php`.

## Seleção administrativa

O dashboard mostra aluno com matrícula e mantém o UUID internamente.

- `public/admin/dashboard.php` contém o seletor explícito de responsável.
- `public/assets/js/admin-dashboard.js` usa UUID como chave de aluno e
  responsável e envia ambos aos endpoints.
- `public/api/admin-view-as-user.php` exige `student_id`, aceita `guardian_id` e
  retorna `GUARDIAN_SELECTION_REQUIRED` quando a escolha é necessária.
- `public/api/admin-upsert-guardian-for-student.php` exige `student_id`; uma
  atualização exige `guardian_id`.
- `public/api/admin-guardians-by-student.php` consulta exclusivamente por
  `student_id`.
- `public/api/admin-charge.php` não possui fallback por nome, valida o vínculo
  responsável-aluno e não atualiza um responsável encontrado globalmente por
  e-mail.

Ao alterar esses fluxos, preserve o rótulo `Nome • Matrícula` e o UUID como valor
real. Nomes servem apenas para apresentação.

## Idempotência e duplicidade

Uma reserva local deve existir antes de uma chamada externa sempre que possível.
Uma repetição não pode criar outra cobrança Asaas.

- `create-payment.php` bloqueia cobrança aberta para a mesma `diaria_id` antes
  de chamar o Asaas.
- Se o Asaas criar a cobrança e a persistência local falhar, o endpoint cancela
  imediatamente a cobrança remota.
- `admin-charge.php` verifica datas abertas por aluno e grava
  `idempotency_key` para a fila manual.
- Ao remover duplicidade com ID Asaas, cancele primeiro no Asaas e somente
  depois remova ou reconcilie o registro local.
- Nunca considere `externalReference` uma garantia de idempotência por si só.

Proteções aplicadas no banco:

- `20260901181800_payments_one_open_per_diaria.sql`: índice parcial único para
  uma cobrança aberta por `diaria_id`.
- `20260901182731_add_payment_idempotency_key.sql`: coluna e índice único de
  `idempotency_key`, além de unicidade para `asaas_payment_id` não nulo.

Estados considerados abertos:

- `queued`;
- `pending`;
- `pending_asaas`;
- `overdue`;
- `awaiting_risk_analysis`.

## Sincronização Asaas

`public/api/admin-sync-recebidas.php` e
`public/api/admin-sync-charges-payments.php` foram endurecidos:

- não usam mais CPF parcial (`ilike`) nem união de resultados de CPF/e-mail;
- exigem uma única correspondência de nome, e-mail e CPF/CNPJ;
- validam a identidade antes de promover status local;
- não associam `asaas_customer_id` quando há ambiguidade;
- contabilizam conflitos bloqueados no resumo;
- não excluem pendência quando a identidade não pode ser comprovada;
- cancelam uma duplicidade remota antes da exclusão local.

Prefira falso negativo seguro: deixar uma conciliação para revisão é melhor que
atribuir pagamento à família errada.

## Banco e RLS

A migration `20260901135800_lock_down_public_data_api.sql`:

- habilita RLS nas 14 tabelas públicas;
- revoga privilégios de `anon` e `authenticated`;
- mantém o backend PHP operando com `service_role`;
- restringe funções públicas e define `search_path`.

Não crie políticas permissivas para eliminar o aviso informativo
`rls_enabled_no_policy`. A ausência de políticas é intencional neste modelo
service-only. Nunca exponha a chave `service_role` ao navegador.

Toda mudança de schema deve:

1. ser criada com o Supabase CLI;
2. verificar previamente conflitos nos dados;
3. ser aplicada por migration;
4. ser confirmada com consulta pós-migration;
5. revisar os advisors de segurança e desempenho.

## Limpeza executada

- O responsável contaminado de Fernando
  (`2de31da3-296e-467f-98ed-bf3633952b9c`) foi colocado em quarentena:
  `parent_document` e `asaas_customer_id` foram limpos.
- O cliente contaminado `cus_000160244939` teve três cobranças vencidas
  canceladas e foi excluído no Asaas.
- As duas cobranças incorretas criadas no incidente foram canceladas no Asaas e
  reconciliadas localmente.
- Na duplicidade de 31/08, `pay_eqnk06u74vkb16jd` foi cancelada e
  `pay_3y1klqdhp9znvjqg` foi preservada.
- Registros locais correspondentes foram marcados como `canceled`; as diárias
  não foram apagadas.
- Endpoints temporários de auditoria e limpeza foram removidos do repositório e
  neutralizados em produção com resposta 404. Não os restaure.

## Testes e verificação

Execute antes de publicar:

```powershell
php tests/asaas_identity_safety_test.php
php tests/admin_settle_payment_security_test.php
php tests/oficina_modular_validity_date_test.php
php -l public/api/admin-charge.php
php -l public/api/admin-sync-recebidas.php
php -l public/api/admin-sync-charges-payments.php
node --check public/assets/js/admin-dashboard.js
```

`tests/asaas_identity_safety_test.php` verifica seleção explícita, identidade
composta, ausência de busca parcial, compensação de cobrança órfã, idempotência
e cancelamento remoto antes da exclusão local.

Após mudanças no JavaScript administrativo, incremente a versão de cache em
`public/admin/dashboard.php` e atualize os testes.

## Histórico da correção

Commits principais, já publicados anteriormente na `main`:

- `d327a19`: bloqueio de conflitos antes de cobrar;
- `fc70acd`: fila incluída no bloqueio de duplicidade;
- `a5fef55`: fechamento do acesso público com RLS;
- `466aa1e`: limpeza financeira e unicidade por diária;
- `e03f201`: sincronização composta, cobrança manual por UUID e idempotência.

O deploy do commit `e03f201` foi concluído com sucesso na Hostinger.
