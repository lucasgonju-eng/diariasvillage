# Diarias Village

SaaS em PHP para compra de diarias do Einstein Village.

## Requisitos
- PHP 8.1+
- Composer

## Instalar
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
