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

- `src/Admin/Dashboard/View/layout.php` contém o seletor explícito de responsável.
- `frontend/admin` usa UUID como chave de aluno e responsável e envia ambos aos
  endpoints; Chamada e Mensalistas também exigem o rótulo `Nome • Matrícula`.
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

## Arquitetura do dashboard administrativo

`public/admin/dashboard.php` é somente o compositor autenticado. A estrutura fica
fora do document root em `src/Admin/Dashboard/View`, com uma partial protegida por
aba, e as consultas ficam em `src/Admin/Dashboard/Data`.

- `DashboardDefinition` é a fonte única da matriz de abas por papel.
- `DashboardDataLoader` não executa loaders financeiros nem de governança para
  `secretaria`.
- A secretaria recebe somente Chamada, Famílias, Sem WhatsApp, Mensalistas e
  Entradas; não renderize shells restritos apenas para escondê-los com CSS.
- O frontend fonte fica em `frontend/admin`, dividido entre `core` e `domains`.
- Vite gera JS e CSS com hash em `public/assets/admin-dist`; esse diretório é
  gerado e não deve ser versionado.
- `src/ViteAssets.php` resolve o manifest e falha fechado se a entrada ou o
  bundle estiver ausente.
- `window.__adminDashboardBooted` só pode ser marcado após todos os
  inicializadores terminarem. O fallback inline apenas mantém navegação básica.
- Não restaure `public/assets/js/admin-dashboard.js`, versão manual por query
  string, `getStudentByName()` ou seleção silenciosa por nome.

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

`src/Services/AsaasPaymentLifecycle.php` centraliza o cancelamento seguro:

- falha de consulta, status desconhecido ou pagamento recebido bloqueiam a
  operação sem mutação local;
- o cliente remoto da cobrança deve corresponder ao vínculo e à identidade
  composta local antes de qualquer cancelamento;
- cobranças abertas são canceladas no Asaas antes de substituição ou
  cancelamento local;
- cobranças novas são canceladas imediatamente se ID, URL ou persistência local
  falharem;
- `payments` e `pendencia_de_cadastro` são marcados como `canceled`, nunca
  apagados fisicamente por ações administrativas;
- pendência sem `asaas_payment_id` é bloqueada para conciliação; ausência de ID
  local não prova ausência de cobrança remota.

`financeiro-pay.php` e `admin-resend-feb-charge.php` reutilizam cobranças
`PENDING`, `OVERDUE` ou `AWAITING_RISK_ANALYSIS` compatíveis. Só substituem uma
cobrança após validar cliente, status e valor e cancelar a anterior. Essas
regras atuam depois da aprovação financeira e não alteram o lançamento
`EM_REVISAO` feito pela secretaria.

Substituições adquirem o estado local `processing_asaas` por compare-and-swap
antes da primeira mutação remota. Isso impede dois cliques concorrentes de
criarem cobranças distintas. Se a compensação remota falhar, o estado permanece
travado para revisão humana. A sincronização só libera esse estado quando
encontra uma resposta completa com exatamente uma cobrança de mesmo token de
operação, valor e identidade composta pela `externalReference`. Cada tentativa
persiste antes da chamada um `asaas_operation_token` aleatório e único. Como o
Asaas não oferece idempotência nativa na criação de cobrança,
`externalReference` é usada apenas para reconciliação, nunca como garantia de
unicidade.

`pendencia_de_cadastro.status = canceled` é terminal por trigger. Tokens antigos,
sincronizações, webhook e baixa manual não podem reativar uma pendência
cancelada. O trigger também sincroniza `status = paid` quando `paid_at` é
preenchido e bloqueia `DELETE` físico da tabela. Sincronizações devem preservar
pendências sem correspondência remota para revisão, pois falha ou paginação
parcial da API não prova ausência de cobrança.

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
- Vínculos do mesmo responsável podem repetir o e-mail somente quando nome,
  documento e `auth_user_id` representam a mesma conta. O trigger
  `trg_guardians_email_identity` bloqueia reutilização entre identidades.
- O primeiro acesso ativa somente o vínculo aluno-responsável confirmado pela
  matrícula e CPF. Outros filhos entram pela solicitação aprovada; nunca agrupe
  automaticamente linhas apenas por CPF e nome.
- A troca de filho no dashboard usa `auth_user_id` e valida o vínculo-aluno;
  CPF, nome ou e-mail isolados não autorizam ampliar a sessão.
- Claims concorrentes ou já concluídos devem bloquear o cadastro.
- `src/GuardianAccountIdentity.php` classifica uma identidade de conta antes de
  login ou reset. CPF com nomes divergentes, mais de um `auth_user_id`, vínculo
  Auth parcial ou múltiplas linhas legadas sem conta comum deve bloquear.
- Login normaliza o CPF e avalia todas as linhas correspondentes. Quando existe
  `auth_user_id`, só aceita a resposta do Supabase Auth com esse UUID e nunca
  retorna ao hash local antigo.
- Reset administrativo possui uma etapa de consulta e exige `guardian_id`
  explícito. A conta Auth é atualizada diretamente pelo UUID, todas as linhas
  dessa conta são revalidadas e a ação entra em `admin_audit_log`.
- Login, reset e expansão familiar não podem usar busca parcial de CPF nem
  escolher silenciosamente a primeira linha ou o primeiro e-mail.
- Logins de responsável e admin usam `LoginThrottle`: três chaves HMAC
  persistidas (IP, conta e combinação), aquisição atômica e resposta `429`.
  Nunca grave CPF, usuário ou IP em texto na tabela de limitação.
- Sessões de responsável expiram por tempo absoluto e inatividade, reconsultam
  o vínculo a cada acesso operacional e exigem `account_session_version`.
  Sessão sem versão não pode ser promovida silenciosamente.
- Troca de senha rotaciona primeiro a versão de todos os vínculos da conta pela
  RPC `rotate_guardian_account_session`; falha posterior no Auth não reativa
  sessões antigas.
- Quando uma conta possui mais de um `student_id`, todo login cria o estado
  bloqueante `family_student_selection_required`. Nenhuma página ou API
  operacional fica disponível antes da escolha explícita em
  `selecionar-aluno.php`.
- O seletor mostra cartões grandes com nome, matrícula e tipo de fluxo
  (mensalista ou day-use). O dashboard destaca permanentemente o aluno ativo e
  oferece `Trocar filho`; nunca restaure seleção automática do primeiro aluno.
- Um pai pode solicitar outro filho somente pela matrícula. A solicitação entra
  em `family_link_requests` e não concede acesso. Secretaria ou admin precisam
  aprovar nominalmente na aba Famílias; a RPC `review_family_link_request`
  revalida conta, aluno, identidade composta e cliente Asaas em transação.
- Não infira irmãos por sobrenome, telefone, e-mail ou semelhança. Em 03/09/2026
  as 27 contas Auth existentes tinham um aluno cada; vínculos anteriores só
  podem ser ampliados por solicitação e aprovação humana.

Na auditoria de 03/09/2026, 153 dos 156 grupos de CPF eram determinísticos
(25 contas Auth compartilhadas e 128 vínculos legados únicos). Três grupos têm
identidades conflitantes e permanecem bloqueados para correção humana; não
contorne o bloqueio para restaurar acesso.

Administradores usam `src/AdminAuth.php`, a tabela `admin_users`, hashes de
senha, roles (`admin_principal` e `secretaria`), sessão versionada e
`admin_audit_log`. Endpoints devem usar `Helpers::requireAdminRole`; não valide
somente flags antigas da sessão. A secretaria pode operar chamada, mensalistas,
entradas e responsáveis sem WhatsApp. Dados financeiros, Asaas, importação,
baixas, reset de senha e mutações sensíveis são exclusivos do admin principal.

A credencial da secretaria é criada e rotacionada exclusivamente pelo admin
principal na aba **Acesso da Secretaria**. A senha entra somente no `POST`
protegido por CSRF, é persistida como hash e nunca vai para ambiente, sessão ou
auditoria. Cada troca incrementa `session_version` e encerra sessões anteriores.
Não existe senha fixa, fallback legado nem bootstrap da secretaria por variável
de ambiente. Se a conta estiver ausente ou marcada com
`requires_password_setup`, o login permanece bloqueado até o admin principal
salvar uma nova senha pelo painel.

Toda mutação administrativa autenticada exige CSRF no helper central. O
financeiro do responsável mostra e opera somente cobranças que correspondam
simultaneamente ao `guardian_id` e ao `student_id` ativos. Criar ou substituir
PIX exige `POST` com CSRF; nunca restaure o proxy financeiro por `GET`.
Logout por link apenas abre confirmação e a mutação ocorre por `POST`, separando
a sessão do responsável da sessão administrativa durante impersonação.

## Chamada presencial e aprovação humana

A chamada presencial é o motor operacional do SaaS e depende de duas pessoas
com papéis distintos:

1. A `secretaria` registra a presença real no dashboard, mesmo quando o aluno
   chegou ao day-use sem diária, PIX ou cobrança previamente criada.
2. O lançamento fica `EM_REVISAO`; registrar presença nunca autoriza nem cria
   cobrança automaticamente.
3. Somente o `admin_principal` pode aceitar ou rejeitar o lançamento.
4. Apenas a aceitação explícita do admin inicia a validação de identidade,
   idempotência e eventual criação da cobrança Asaas.
5. A rejeição preserva o histórico e não gera cobrança. Mensalistas permanecem
   cobertos pelo plano e também não geram cobrança.

Nunca exija cobrança prévia para a secretaria registrar presença, nunca remova
essa revisão humana e nunca transforme o fechamento da chamada em emissão
automática. Correções financeiras devem preservar integralmente esse fluxo.

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

Na auditoria de 03/09/2026 havia 50 cobranças Asaas abertas anteriores a
setembro vinculadas aos atuais mensalistas, além de duas filas locais antigas.
A cobrança de Julia Andrade Diniz (`pay_ldlnzizxs2acbyt6`) foi nominalmente
autorizada, cancelada no Asaas e marcada como `canceled` no registro local.
Permanecem como dívida futura:

- 30 cobranças remotas abertas que passaram em vínculo, identidade composta e
  valor, mas ainda dependem de autorização nominal e confirmação da vigência
  histórica do plano;
- 19 cobranças remotas abertas bloqueadas por conflito de cliente, identidade
  composta ou valor;
- 2 filas locais sem `asaas_payment_id`, cuja ausência de ID não prova que não
  houve criação remota.

Não cancele esses registros em lote. Audite identidade, competência e situação
remota individualmente e cancele no Asaas antes de reconciliar localmente.

`public/api/admin-monthly-legacy-charge-audit.php` é o inventário administrativo
somente leitura desses registros. Ele consulta cada ID Asaas exato, confere
vínculo responsável-aluno, cliente, identidade composta e valor, e nunca executa
mutação. Resultado pago, fechado, ausente, desconhecido ou conflitante permanece
para reconciliação humana; mesmo uma cobrança aberta integralmente validada não
é autorização automática de cancelamento.

Quando o usuário autorizar um item nominal, `admin-delete-payment.php` aceita o
motivo `MENSALISTA_COBERTO_PELO_PLANO` somente para plano ativo e cobrança
anterior a setembro de 2026, cancela primeiro no Asaas e registra esse motivo
exato na trilha local. Não reutilize o motivo de duplicidade nesse caso.

## Banco e RLS

A migration `20260901135800_lock_down_public_data_api.sql`:

- habilita RLS nas 14 tabelas públicas;
- revoga privilégios de `anon` e `authenticated`;
- mantém o backend PHP operando com `service_role`;
- restringe funções públicas e define `search_path`.

Não crie políticas permissivas para eliminar o aviso informativo
`rls_enabled_no_policy`. A ausência de políticas é intencional neste modelo
service-only. Nunca exponha a chave `service_role` ao navegador.

A migration `20260903155027_index_remaining_foreign_keys.sql` cobre as nove
chaves estrangeiras antigas que ainda não tinham índice. Não remova esses
índices apenas porque o advisor os classifica como `unused_index` logo após a
criação; eles protegem consultas, exclusões e atualizações das linhas
referenciadas à medida que as tabelas crescem.

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
 legado ainda permanecer no document root.

## Deploy atômico e rollback

O deploy da Hostinger usa releases imutáveis em
`$SSH_TARGET_DIR/.releases/<commit>-<run_id>`. O document root mantém um
dispatcher estável e a ativação troca atomicamente apenas o symlink
`.releases/current`.

- Nunca volte a copiar o pacote diretamente sobre `$SSH_TARGET_DIR`.
- A release deve ser validada por PHP CLI antes da troca e por
  `https://diarias.village.einsteinhub.co/health.php` depois dela.
- Falha após o início da ativação restaura o symlink anterior; na primeira
  migração, restaura o `.htaccess` do deploy legado.
- `.env` permanece fora do document root e entra na release por symlink.
- `storage` permanece no document root como estado persistente e entra na
  release por symlink; não copie nem apague esse diretório em uma publicação.
- Acesso HTTP direto a `.releases`, `storage`, `.trash` e `.legacy-root` é
  bloqueado pelo dispatcher antes do rewrite interno.
- O workflow mantém cinco releases e usa concorrência serializada, sem cancelar
  uma publicação já em andamento.
- O deploy manual separado foi removido; `workflow_dispatch` usa exatamente o
  mesmo pipeline testado do push em `main`.
- `public/health.php` é somente leitura, não inicia probes em disco e retorna o
  identificador presente em `release-manifest.json`.

## Saída HTML e navegação no navegador

- Dados de aluno, responsável, cobrança ou Asaas nunca entram em `innerHTML`
  sem `escapeHtml`; prefira `textContent` e criação explícita de elementos.
- JSON emitido dentro de `<script>` deve usar `JSON_HEX_TAG`, `JSON_HEX_AMP`,
  `JSON_HEX_APOS` e `JSON_HEX_QUOT`.
- A prévia visual de e-mail sanitiza elementos executáveis, atributos `on*`,
  estilos com URL e protocolos perigosos. Colagem rica não pode inserir HTML
  diretamente.
- Placeholders vindos do banco são escapados antes de entrar no corpo HTML de
  e-mails. O assunto continua sendo tratado como texto.
- URLs externas abertas pelo navegador exigem HTTPS. Links rotulados como
  Asaas devem pertencer a `asaas.com` ou subdomínio; redirecionamentos internos
  devem permanecer na mesma origem.
- Mudanças nesses controles devem manter `tests/xss_security_test.php` e
  avançar as versões de cache dos JavaScripts alterados.

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
php tests/asaas_customer_identity_behavior_test.php
php tests/admin_settle_payment_security_test.php
php tests/oficina_modular_validity_date_test.php
php tests/oficina_modular_authorization_test.php
php tests/payment_lifecycle_behavior_test.php
php tests/authentication_security_test.php
php tests/guardian_account_identity_test.php
php tests/family_student_selection_test.php
php tests/login_session_security_test.php
php tests/xss_security_test.php
php tests/monthly_workshop_security_test.php
php tests/monthly_legacy_charge_audit_test.php
php tests/deploy_release_security_test.php
php tests/admin_dashboard_contract_test.php
php tests/admin_dashboard_data_loader_test.php
php tests/admin_dashboard_view_rbac_test.php
php tests/vite_assets_test.php
php tests/vite_assets_flattened_release_test.php
php tests/exclusion_log_storage_test.php
npm ci
npm audit --audit-level=high
npm run check
php -l public/api/admin-charge.php
php -l public/api/admin-sync-recebidas.php
php -l public/api/admin-sync-charges-payments.php
```

`tests/asaas_identity_safety_test.php` verifica seleção explícita, identidade
composta, ausência de busca parcial, compensação de cobrança órfã, idempotência
e cancelamento remoto antes da exclusão local.

Após mudanças no frontend administrativo, gere novamente o bundle. O hash do
Vite substitui a versão manual de cache; não edite o manifest nem os arquivos
em `public/assets/admin-dist` diretamente.

## Histórico da correção

Commits principais, já publicados anteriormente na `main`:

- `d327a19`: bloqueio de conflitos antes de cobrar;
- `fc70acd`: fila incluída no bloqueio de duplicidade;
- `a5fef55`: fechamento do acesso público com RLS;
- `466aa1e`: limpeza financeira e unicidade por diária;
- `e03f201`: sincronização composta, cobrança manual por UUID e idempotência.

O deploy do commit `e03f201` foi concluído com sucesso na Hostinger.
