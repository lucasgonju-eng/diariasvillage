<?php
require_once __DIR__ . '/../src/Bootstrap.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Primeiro acesso</title>
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">Diarias Village</div>
      <nav class="nav">
        <a class="button secondary" href="/login.php">Entrar</a>
      </nav>
    </header>

    <div class="card">
      <h2>Primeiro acesso</h2>
      <p class="subtitle">Confirme o aluno, cadastre o e-mail e defina a senha.</p>

      <form id="register-form">
        <div class="form-group">
          <label>Nome do aluno</label>
          <input id="student-name" list="students-list" placeholder="Digite o nome do aluno" required />
          <datalist id="students-list"></datalist>
          <div class="small">Somente alunos do 6º ao 8º ano.</div>
        </div>

        <div class="form-group">
          <label>E-mail do responsavel</label>
          <input type="email" id="email" placeholder="email@exemplo.com" required />
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label>Senha</label>
            <input type="password" id="password" required />
          </div>
          <div class="form-group">
            <label>Confirmar senha</label>
            <input type="password" id="password-confirm" required />
          </div>
        </div>

        <button class="button" type="submit">Primeiro acesso</button>
        <div id="form-message"></div>
      </form>
    </div>
  </div>

  <script src="/assets/js/app.js"></script>
</body>
</html>
