# Resumo Executivo - Migracao de Hostname

## Identificacao da Mudanca

- **Produto:** SaaS Diarias Village
- **Hostname anterior:** `https://village.einsteinhub.co`
- **Hostname novo:** `https://diarias.village.einsteinhub.co`
- **Data da validacao final:** 2026-03-07
- **Responsavel tecnico pela execucao assistida:** Equipe interna + assistente tecnico

## Objetivo

Migrar a operacao do Diarias Village para hostname dedicado, preservando estabilidade dos fluxos criticos:

- login/autenticacao,
- criacao de pagamento,
- processamento de webhook Asaas,
- links de retorno e navegação,
- disponibilidade web/mobile.

## Escopo Executado

- Ajustes de codigo para remover dependencias hardcoded no hostname antigo.
- Atualizacao de exemplos e padroes de configuracao (`.env.example` e `README`).
- Correcao de infraestrutura DNS/hosting/SSL para o novo subdominio.
- Validacao de integracao com Asaas (webhook ativo no hostname novo).
- Teste funcional ponta a ponta de pagamento em producao.

## Resultado dos Testes Criticos

- **DNS do novo host:** aprovado.
- **SSL/TLS do novo host:** aprovado.
- **Aplicacao web no novo host (`/`, `/login.php`):** aprovado.
- **Aplicacao mobile (`/mobile/`):** aprovado (redirecionamento esperado para login sem sessao).
- **Webhook Asaas no novo host:** aprovado e protegido por token.
- **Fluxo real de pagamento:** aprovado (login -> diaria -> checkout -> create-payment).

## Riscos Residuais

- Necessidade de manter disciplina operacional para futuras mudancas no Asaas (evitar regressao de webhook/callback para host antigo).
- Eventuais inconsistencias de cache DNS local em clientes isolados nas primeiras horas apos alteracoes.

## Decisao

- **GO** para operacao do Diarias Village em `https://diarias.village.einsteinhub.co`.

## Plano de Rollback (simples)

Se ocorrer incidente critico:

1. Reverter `APP_URL` para `https://village.einsteinhub.co`.
2. Reverter webhook Asaas para `https://village.einsteinhub.co/api/asaas-webhook.php`.
3. Reexecutar smoke test minimo (`/`, login, 1 pagamento de validacao).

## Evidencias Principais (resumo)

- Host novo respondendo `200 OK`.
- Webhook Asaas cadastrado no hostname novo.
- Pagamento real criado com sucesso no fluxo do host novo.
- Documento tecnico completo disponivel em `docs/auditoria-migracao-hostname-diarias-village.md`.
