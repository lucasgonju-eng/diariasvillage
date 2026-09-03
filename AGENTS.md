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

## Webhook Asaas

`public/api/asaas-webhook.php` opera em modo fail-closed:

- aceita somente `POST` e o cabeçalho oficial `asaas-access-token`;
- recusa todas as chamadas se `ASAAS_WEBHOOK_TOKEN` estiver ausente;
- compara o segredo com `hash_equals` e nunca registra o valor recebido;
- limita o JSON a 256 KB e valida `event.id`, tipo e `payment.id`;
- registra e adquire cada evento pela RPC `claim_asaas_webhook_event`;
- usa `event.id` como chave idempotente e bloqueia payload divergente;
- consulta o pagamento e o cliente diretamente no Asaas antes de promover
  status local;
- exige valor, vínculo responsável-aluno e identidade composta compatíveis;
- não concilia pendência por URL da fatura nem por CPF isolado;
- retorna resposta não-2xx após falha transitória para que o Asaas tente
  novamente;
- registra conflito permanente como `BLOCKED` e responde 2xx, evitando tentativas
  inúteis que poderiam pausar toda a fila do webhook.

`asaas_webhook_events` é uma caixa de entrada service-only com RLS. Os estados
são `RECEIVED`, `PROCESSING`, `PROCESSED`, `IGNORED`, `BLOCKED` e `FAILED`.
Uma trava de processamento expira após cinco minutos para permitir recuperação
de encerramento inesperado. Não remova a validação remota nem responda sucesso
antes de concluir ou bloquear o evento no banco.

## Primeiro acesso e autenticação administrativa

O primeiro acesso do responsável continua público e simples, mas é estritamente
único. `register-primeiro-acesso.php` exige `student_id`, valida o vínculo entre
CPF e aluno e usa as RPCs `begin_first_access_claim`,
`complete_first_access_claim` e `cancel_first_access_claim`.

- `public/api/register.php` é uma tombstone 404 permanente. Não restaure o
  cadastro legado por nome do aluno nem permita criar responsável com dados
  fornecidos pelo solicitante sem validar o vínculo existente.
- Nunca redefina um usuário existente do Supabase Auth no primeiro acesso.
- Uma criação Auth sem conclusão local deve ser compensada com exclusão imediata.
- O e-mail novo é salvo apenas no vínculo principal porque `guardians.email` é
  único. Os demais filhos da mesma identidade recebem o mesmo `auth_user_id`.
- A troca de filho no dashboard usa `auth_user_id` e valida o vínculo-aluno;
  CPF, nome ou e-mail isolados não autorizam ampliar a sessão.
- Claims concorrentes ou já concluídos devem bloquear o cadastro.
- Login e expansão familiar não podem usar busca parcial de CPF.

Administradores usam `src/AdminAuth.php`, a tabela `admin_users`, hashes de
senha, roles (`admin_principal` e `secretaria`), sessão versionada e
`admin_audit_log`. Endpoints devem usar `Helpers::requireAdminRole`; não valide
somente flags antigas da sessão. A secretaria pode operar chamada, mensalistas,
entradas e responsáveis sem WhatsApp. Dados financeiros, Asaas, importação,
baixas, reset de senha e mutações sensíveis são exclusivos do admin principal.

`SECRETARIA_SECRET` deve ser configurado no ambiente. Até isso ocorrer, existe
um fallback legado temporário e auditado. Remova o fallback assim que o segredo
for configurado.

## Autorização da grade de oficinas

Seleção e remoção de Oficina Modular são sempre vinculadas ao responsável da
sessão. `OficinaModularGradeService` consulta a diária por `id` e `guardian_id`
e aceita mutação somente enquanto ela está `PENDENTE` e não está travada.

As RPCs `oficina_modular_grade_travar_e_reservar` e
`oficina_modular_grade_liberar_e_cancelar` recebem `p_guardian_id`, repetem a
validação com `FOR UPDATE` e são executáveis somente por `service_role`.
Nunca restaure as assinaturas antigas sem responsável nem confie apenas no UUID
da diária recebido pela rota.

## Alunos mensalistas

A fonte histórica é a aba **Mensalistas** do admin. Em 02/09/2026, os 60 planos
ativos dessa aba foram migrados para `monthly_student_plans`: 8 de dois dias,
4 de três dias, 2 de quatro dias e 46 de cinco dias.

- Mensalista nunca gera PIX de diária.
- A franquia é exatamente `2 × weekly_days` encontros por semana.
- A seleção deve ocupar exatamente `weekly_days` dias, com dois horários em cada
  dia; os dias são determinados pelas oficinas escolhidas.
- Oficinas `ALL_MEETINGS` ocupam todos os encontros cadastrados.
- Trilhas do Conhecimento é `SINGLE_MEETING` e ocupa somente o horário escolhido.
- Escolha pela Orientadora ocupa um encontro e fica registrada.
- A confirmação cria entradas recorrentes para todas as datas correspondentes
  da competência.
- Uma confirmação ativa é imutável. Alteração de franquia ou desativação exige
  desbloqueio administrativo prévio, que cancela as entradas anteriores.
- Sempre identifique plano por `student_id`; nome não é fallback.

As tabelas `monthly_student_plans`, `monthly_workshop_submissions`,
`monthly_workshop_slots` e `monthly_workshop_entries` têm RLS e acesso
service-only. As RPCs `confirm_monthly_workshops` e
`unlock_monthly_workshops` fazem as mutações transacionais.

Existem 50 cobranças Asaas abertas anteriores a setembro vinculadas aos atuais
mensalistas, além de duas filas locais antigas. Não cancele esses registros em
lote: audite identidade, competência e situação remota individualmente e
cancele no Asaas antes de reconciliar localmente.

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

## Importações e dependências

O PhpSpreadsheet deve permanecer em `1.30.6` ou versão 1.x posterior sem
advisories. A versão 1.30.2 possuía nove vulnerabilidades conhecidas, incluindo
SSRF/RCE no carregamento de arquivos enviados.

- Todo upload administrativo deve passar por `src/UploadSecurity.php`.
- Valide erro de upload, tamanho real, extensão e MIME; o atributo `accept` do
  navegador não é controle de segurança.
- A importação de alunos aceita somente CSV, XLS e XLSX até 5 MB, no máximo
  5.000 alunos e 50 colunas.
- Escolha o leitor XLS/XLSX explicitamente; não restaure
  `IOFactory::load()` com caminho controlado por upload.
- A importação de responsáveis aceita PDF até 5 MB ou JSON até 2 MB e limita
  cada operação a 5.000 responsáveis.
- Execute `composer audit --locked` antes de publicar alterações de
  dependências.
- `public/` não pode conter backups nem arquivos com extensões `.bak`, `.bkp`,
  `.old`, `.orig`, `.save`, `.swp`, `.sql`, `.zip`, `.tar`, `.gz` ou `.7z`.
- O `.htaccess` nega essas extensões e o workflow deve rejeitá-las no pacote.
  Não remova a limpeza dos seis backups legados do servidor enquanto o deploy
  continuar sendo feito por sobreposição.

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
php tests/upload_security_test.php
php tests/asaas_webhook_security_test.php
php tests/asaas_identity_safety_test.php
php tests/admin_settle_payment_security_test.php
php tests/oficina_modular_validity_date_test.php
php tests/oficina_modular_authorization_test.php
php tests/authentication_security_test.php
php tests/monthly_workshop_security_test.php
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
