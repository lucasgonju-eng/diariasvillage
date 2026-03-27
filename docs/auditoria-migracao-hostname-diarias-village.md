# Auditoria Completa de Migracao de Hostname - Diarias Village

## Executive Summary

A migracao de `https://village.einsteinhub.co` para `https://diarias.village.einsteinhub.co` esta tecnicamente viavel e foi validada em infraestrutura (DNS, subdominio ativo e HTTPS funcional).

Os pontos de maior risco estavam em fallbacks hardcoded de hostname no backend de pagamentos/webhooks/e-mails e no fluxo mobile. Esses pontos foram corrigidos no codigo durante esta execucao.

O sistema no host novo responde com sucesso e os endpoints criticos estao ativos. Restam pendencias operacionais fora do codigo (principalmente configuracoes no painel da Asaas e conferencia do `.env` de producao).

## Critical Risks

- Asaas webhook/callback ainda pode estar apontando para o host antigo no painel da Asaas.
- `APP_URL` no `.env` de producao pode continuar no host antigo, impactando links absolutos em e-mails/fluxos.
- Enderecos de e-mail (`SMTP_USER`/`EMAIL_FROM`) precisam existir no dominio final escolhido para evitar rejeicao SMTP.

## Medium Risks

- Cache/DNS local pode mascarar validacoes logo apos mudancas de zona/subdominio.
- Sessao/cookies por host geram novo login no dominio novo (comportamento esperado, mas operacionalmente sensivel).
- Documentacao e exemplos desatualizados podem induzir configuracao de deploy em host antigo.

## Non-Relevant Risks

- Rotas frontend/backend internas usam majoritariamente caminhos relativos e nao dependem de hostname fixo.
- Supabase URL do projeto correto foi alinhada para `rftkojyxrcxvkzngwbcd`.

## Code Findings

### Corrigidos

- `public/api/create-payment.php`
  - fallback de `Helpers::baseUrl()` atualizado para `https://diarias.village.einsteinhub.co`.
- `public/api/asaas-webhook.php`
  - dois fallbacks de portal link atualizados para `https://diarias.village.einsteinhub.co`.
- `public/api/admin-send-pending-charges.php`
  - fallback de portal link atualizado para `https://diarias.village.einsteinhub.co`.
- `public/api/admin-link-pendencia-by-asaas.php`
  - fallback de portal link atualizado para `https://diarias.village.einsteinhub.co`.
- `public/api/admin-settle-pendencia.php`
  - fallback de portal link atualizado para `https://diarias.village.einsteinhub.co`.
- `public/mobile/index.php`
  - fallback de host atualizado para `diarias.village.einsteinhub.co`.
- `public/mobile/simulator-iphone16.php`
  - host/URL de fallback atualizado para `https://diarias.village.einsteinhub.co/mobile/`.
- `public/mobile/simulator-iphone16.html`
  - host/URL de fallback atualizado para `https://diarias.village.einsteinhub.co/mobile/`.
- `.env.example`
  - `APP_URL`, `SMTP_USER`, `EMAIL_FROM`, `EMAIL_SECRETARIA` ajustados para o hostname novo.
- `README.md`
  - exemplos de FTP e webhook da Asaas atualizados para o hostname novo.
- `src/Mailer.php`
  - fallback default de `EMAIL_FROM` atualizado para o hostname novo.

### Evidencia tecnica

- Lint/sintaxe PHP validada sem erros nos arquivos alterados (`php -l`).
- Busca por hardcode antigo nos arquivos criticos nao retorna mais `village.einsteinhub.co` como fallback ativo.

## Configuration Findings

### Resolvido

- DNS autoritativo: `diarias.village.einsteinhub.co` criado como subdominio de `village.einsteinhub.co`.
- Hosting: subdominio criado e ativo no Hostinger (`vhost_type: subdomain`, `is_enabled: true`).
- SSL/TLS: HTTPS funcional no hostname novo.

### Pendente (externo ao repositorio)

- Confirmar no painel da Asaas:
  - Webhook: `https://diarias.village.einsteinhub.co/api/asaas-webhook.php`
  - Callback de sucesso (quando aplicavel): `https://diarias.village.einsteinhub.co/pagamento-retorno.php?...`
- Confirmar `.env` real de producao:
  - `APP_URL=https://diarias.village.einsteinhub.co`
  - remetente SMTP e caixas de e-mail existem e estao autorizadas.

## Change Checklist (Pre-Migration)

- [x] Validar DNS do host novo em resolvedores publicos.
- [x] Validar criacao do subdominio no hosting.
- [x] Validar emissao/funcionamento de SSL no host novo.
- [x] Corrigir hardcodes de hostname em pagamento, webhook, e-mails e mobile.
- [x] Atualizar docs e exemplos de configuracao.
- [ ] Atualizar painel da Asaas (webhook/callback).
- [ ] Confirmar `.env` de producao no servidor com `APP_URL` novo.
- [ ] Confirmar identidade SMTP/remetente final (dominio e conta autorizada).

## Post-Migration Test Checklist

- [x] `GET /` no host novo retorna `200`.
- [x] `GET /login.php` no host novo retorna `200`.
- [x] `GET /mobile/` no host novo redireciona para login (`302` esperado).
- [x] `GET /api/asaas-webhook.php` retorna `401 Token invalido` (endpoint ativo e protegido).
- [x] `GET /api/profile.php` retorna `405 Metodo invalido` para GET (endpoint ativo).
- [ ] Teste funcional real de login no host novo.
- [ ] Teste de criacao de pagamento real no host novo.
- [ ] Teste de confirmacao via webhook Asaas real (`PAYMENT_CONFIRMED`/`PAYMENT_RECEIVED`).
- [ ] Teste de e-mail transacional (responsavel + secretaria) com links absolutos no host novo.
- [ ] Teste de rollback operacional (voltar webhook/callback para host antigo em contingencia).

## Final Recommendation

**Status atual: GO condicional (operacional).**

A camada tecnica de infraestrutura e codigo esta preparada para operar no novo hostname. Para evitar quebra de pagamento/webhook/e-mail, a entrada em producao deve acontecer somente apos concluir as 3 pendencias externas:

1. atualizar Asaas (webhook/callback),
2. confirmar `.env` de producao com `APP_URL` novo,
3. validar remetente SMTP autorizado.

Com esses itens concluídos e checklist funcional executado, a migracao para `https://diarias.village.einsteinhub.co` fica recomendada.
