# Diarias Village

SaaS em PHP para compra de diarias do Einstein Village.

## Deploy automatico (GitHub → Hostinger)

A cada push em `main`, o GitHub Actions faz upload via FTP para o Hostinger.

### 1. Configure os secrets no GitHub

Em **Settings → Secrets and variables → Actions**, adicione:

| Secret | Onde pegar |
|--------|------------|
| `FTP_SERVER` | Hostinger → FTP Accounts → Servidor (ex: `ftp.village.einsteinhub.co` ou o host FTP) |
| `FTP_USERNAME` | Usuario FTP do Hostinger |
| `FTP_PASSWORD` | Senha do usuario FTP |
| `FTP_SERVER_DIR` | Pasta remota onde subir (ex: `/domains/village.einsteinhub.co`). No Hostinger, costuma ser a pasta do dominio. |

### 2. Document root no Hostinger

Defina o **document root** como a pasta `public_html` dentro do dominio (e o que o deploy criar).

### 3. Arquivo .env no servidor

O deploy nao envia o `.env`. Crie manualmente no servidor (na mesma pasta que `src` e `vendor`) copiando do `.env.example` e preenchendo as chaves.

---

## Instalar (local)
1. Copie `.env.example` para `.env` e preencha as chaves.
2. `composer install`
3. Crie o schema no Supabase usando `supabase/schema.sql`.
4. Configure o webhook da Asaas para `https://village.einsteinhub.co/api/asaas-webhook.php`.

## Pastas
- `public/`: arquivos publicos e endpoints
- `src/`: codigo PHP
- `supabase/`: schema do banco

## Aviso
Nunca commitar `.env`.
