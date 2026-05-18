# Registro - Cadastro manual de alunos

Data: 18/05/2026

## Contexto

O SaaS está funcionando bem no fluxo principal, mas havia dificuldade operacional para cadastrar novos alunos quando o caminho por importação de planilha não era o ideal.

A orientação foi adicionar, com o mínimo de risco possível, uma opção na área administrativa de **Importar Alunos** para **criar aluno manualmente**, sem quebrar ou alterar o fluxo que já está em produção.

## Objetivo da alteração

Permitir que o admin principal cadastre um novo aluno diretamente pela tela `/admin/import.php`, informando os dados essenciais usados pelo sistema:

- Nome do aluno.
- Matrícula.
- Série.
- Turma.
- Data de nascimento.
- Nome do responsável principal.
- E-mail do responsável principal.
- WhatsApp do responsável principal.
- CPF do responsável principal.

## O que foi feito

### 1. Novo formulário manual em Importar Alunos

Arquivo alterado:

- `public/admin/import.php`

Foi adicionado um novo card chamado **Criar aluno manualmente** abaixo do card atual de importação de alunos por arquivo.

O formulário de importação existente foi preservado:

- Continua usando `/api/import-students.php`.
- Continua aceitando CSV, XLS e XLSX.
- Não teve a lógica de upload alterada.

O novo formulário manual envia os dados via `fetch` para um endpoint novo e isolado:

- `/api/admin-create-student.php`

A tela mostra mensagem de sucesso ou erro sem recarregar a página.

### 2. Novo endpoint isolado para criação manual

Arquivo criado:

- `public/api/admin-create-student.php`

Esse endpoint foi criado separado do importador para reduzir o risco de impacto no fluxo atual.

Ele faz:

- Validação de sessão administrativa.
- Restrição ao admin principal.
- Validação dos dados obrigatórios.
- Validação da série permitida: 6, 7 ou 8.
- Validação de data de nascimento no formato `Y-m-d`, quando informada.
- Validação de e-mail do responsável.
- Validação de CPF do responsável com 11 dígitos.
- Bloqueio de matrícula duplicada.
- Bloqueio de aluno duplicado por nome.
- Bloqueio de e-mail de responsável já existente.
- Criação do aluno ativo na tabela `students`.
- Criação do responsável vinculado na tabela `guardians`.

### 3. Proteção contra cadastro parcial

Foi incluído cuidado para não deixar dados pela metade.

Se o aluno for criado, mas a criação do responsável falhar, o endpoint tenta remover o aluno recém-criado e retorna uma mensagem clara para revisar os dados e tentar novamente.

## O que não foi alterado

Para proteger o que já está funcionando, não foram alterados:

- O endpoint `/api/import-students.php`.
- O endpoint `/api/import-guardians.php`.
- O dashboard administrativo principal.
- Fluxos de cobrança.
- Fluxos de chamada.
- Fluxos de mensalistas.
- Fluxos do primeiro acesso.
- Estrutura de banco de dados.

## Validações executadas

Foram executadas as seguintes validações:

```bash
php -l public/api/admin-create-student.php
php -l public/admin/import.php
git diff --check -- public/admin/import.php public/api/admin-create-student.php
```

Resultado:

- Nenhum erro de sintaxe PHP.
- Nenhum erro de linter nos arquivos alterados.
- Nenhum problema de whitespace no diff.

Observação: o Git informou apenas que `public/admin/import.php` pode trocar LF por CRLF quando for tocado pelo Git, o que é aviso de final de linha no Windows e não erro da alteração.

## Estado atual antes deste commit

Arquivos relacionados à alteração:

- `public/admin/import.php`
- `public/api/admin-create-student.php`
- `REGISTRO_CADASTRO_MANUAL_ALUNOS.md`

Arquivos não relacionados já existentes no working tree e que não devem entrar neste commit:

- `error_log_custom.txt`
- `mobile.zip`

## O que estamos fazendo agora

Estamos registrando esta alteração em commit Git, com escopo limitado ao cadastro manual de alunos e à documentação deste registro.

O commit deve incluir somente:

- A tela com o novo formulário manual.
- O endpoint novo de criação manual.
- Este arquivo Markdown de documentação.

Não será incluído no commit:

- `error_log_custom.txt`
- `mobile.zip`

## Próximos cuidados recomendados

Após subir a alteração para o ambiente, testar manualmente:

- Criar aluno novo com responsável novo.
- Tentar repetir matrícula já existente.
- Tentar repetir nome já existente.
- Tentar usar e-mail de responsável já existente.
- Conferir se o aluno aparece nas listas administrativas.
- Conferir se o responsável consegue seguir o fluxo de primeiro acesso quando aplicável.
