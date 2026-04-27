# Diarias Village

SaaS em PHP para compra de diárias do Einstein Village (6º ao 8º ano).

## Deploy automatico (GitHub → Hostinger)

A cada push em `main`, o GitHub Actions faz upload via FTP para o Hostinger.

### 1. Configure os secrets no GitHub

Em **Settings → Secrets and variables → Actions**, adicione:

| Secret | Onde pegar |
|--------|------------|
| `FTP_SERVER` | Hostinger → FTP Accounts → Servidor (ex: `ftp.diarias.village.einsteinhub.co` ou o host FTP) |
| `FTP_USERNAME` | Usuario FTP do Hostinger |
| `FTP_PASSWORD` | Senha do usuario FTP |
| `FTP_SERVER_DIR` | Pasta remota onde subir (ex: `/domains/diarias.village.einsteinhub.co`). No Hostinger, costuma ser a pasta do dominio. |

### 2. Document root no Hostinger

Defina o **document root** como a pasta `public_html` do dominio.

### 3. Arquivo .env no servidor

O deploy nao envia o `.env`. Crie manualmente no servidor **dentro de `public_html`**, ao lado de `src` e `vendor`, copiando do `.env.example` e preenchendo as chaves.

---

## Instalar (local)
1. Copie `.env.example` para `.env` e preencha as chaves.
2. `composer install`
3. Crie o schema no Supabase usando `supabase/schema.sql`.
4. Configure o webhook da Asaas para `https://diarias.village.einsteinhub.co/api/asaas-webhook.php`.

## Pastas
- `public/`: arquivos publicos e endpoints
- `src/`: codigo PHP
- `supabase/`: schema do banco

## Aviso
Nunca commitar `.env`. 

---

## Incidente: cliente nao consegue login por CPF nao vinculado

### Sintomas vistos nas telas
- Login com CPF do responsavel e senha retorna `Credenciais invalidas.`
- Criacao de conta/primeiro acesso retorna `CPF nao encontrado no cadastro.`
- O formulario de pendencia pode aparecer como alternativa; se ele falhar, a secretaria deve registrar a pendencia manualmente no admin ou corrigir o cadastro diretamente no banco.

### Diagnostico do caso de 27/04/2026
- CPF informado pela cliente: `000.442.791-24` (`00044279124` normalizado pelo sistema).
- Aluna informada: Amanda Caramaschi Teixeira Soares.
- E-mail informado: Soareskarla885@gmail.com.
- Day-use pretendido: 28/04/2026.
- Consulta de primeiro acesso em producao (`/api/primeiro-acesso-students.php`) retornou `CPF nao encontrado no cadastro.`
- No banco, a Amanda existia e estava ativa, mas o unico responsavel vinculado a ela usava o mesmo e-mail com nome/CPF de outro responsavel.

### Causa raiz
O login e o primeiro acesso dependem da tabela `guardians`. Para funcionar, precisa existir ao menos um registro em `guardians` com:

- `parent_document` igual ao CPF normalizado, somente numeros.
- `student_id` apontando para a aluna correta em `students`.
- `email` valido.
- `password_hash` e `verified_at` preenchidos depois que a responsavel concluir/criar a conta.

Quando o CPF nao existe em `guardians`, o login cai em `Credenciais invalidas` e o primeiro acesso cai em `CPF nao encontrado no cadastro`. Isso nao e erro de senha; e falta de vinculo do responsavel no cadastro.

### Solucao operacional
1. No admin, localizar a aluna pelo nome completo.
2. Conferir se Amanda Caramaschi Teixeira Soares existe e esta ativa em `students`.
3. Criar ou corrigir o responsavel vinculado a essa aluna em `guardians`:
   - nome: Karla Pereira Soares Caramaschi;
   - CPF: `00044279124`;
   - e-mail: `Soareskarla885@gmail.com`;
   - `student_id`: ID da Amanda;
   - manter o CPF sem pontos e traco.
4. Depois disso, pedir para a responsavel usar `Criar conta`/primeiro acesso para definir a senha, ou redefinir a senha pelo admin se a conta ja existir.
5. Validar novamente em `/api/primeiro-acesso-students.php`: o retorno esperado deve ser `ok: true` com a aluna como candidata.

### Correcao aplicada em 27/04/2026
- Atualizado o registro existente em `guardians` vinculado a Amanda para:
  - nome: Karla Pereira Soares Caramaschi;
  - CPF: `00044279124`;
  - e-mail: `Soareskarla885@gmail.com`.
- Validacao em producao apos a correcao: `/api/primeiro-acesso-students.php` retornou `ok: true` com Amanda Caramaschi Teixeira Soares como candidata.

### Prevencao
- Em importacoes de alunos/responsaveis, sempre normalizar CPF para somente numeros antes de salvar em `guardians.parent_document`.
- Depois de importar, testar uma amostra de CPFs no primeiro acesso para garantir que o vinculo `guardian -> student` existe.
- Se o cliente reportar `CPF nao encontrado no cadastro`, nao tratar como problema de senha. Primeiro conferir o vinculo em `guardians`.
- Se o formulario de pendencia falhar, verificar logs do endpoint `/api/pendencia-cadastro.php` e criar a pendencia/cadastro pelo admin para nao bloquear a diaria.

### Erro ao alterar senha no perfil
- Sintoma: DevTools mostra `api/profile.php 500` e `Unexpected end of JSON input`.
- Causa: o frontend tentava interpretar como JSON uma resposta 500 vazia/inesperada do endpoint de perfil.
- Correcao aplicada: `api/profile.php` agora valida payload/sessao, sempre responde JSON em falhas e sincroniza a nova senha com o Supabase Auth quando o e-mail do responsavel existe no Auth. O `profile.js` tambem passou a ler a resposta como texto antes de parsear JSON, exibindo mensagem amigavel quando o servidor falha.
- Ajuste posterior: a rota de atualizacao do Supabase Auth foi corrigida para `/auth/v1/admin/users/{id}`. Se a sincronizacao externa falhar, o perfil nao bloqueia a tela porque a senha local em `guardians.password_hash` ja foi atualizada e o login possui fallback local.

