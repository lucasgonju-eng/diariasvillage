<?php
require_once __DIR__ . '/src/Bootstrap.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Entrar</title>
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">Diarias Village</div>
      <nav class="nav">
        <a class="button secondary" href="/">Voltar</a>
      </nav>
    </header>

    <div class="card">
      <h2>Entrar</h2>
      <p class="subtitle">Acesse com o e-mail verificado.</p>

      <form id="login-form">
        <div class="form-group">
          <label>E-mail</label>
          <input type="email" id="login-email" required />
        </div>
        <div class="form-group">
          <label>Senha</label>
          <input type="password" id="login-password" required />
        </div>
        <button class="button" type="submit">Entrar</button>
        <div id="login-message"></div>
      </form>
    </div>
    <div class="footer">Desenvolvido por Lucas Goncalves Junior - 2026</div>
  </div>
  <script src="/assets/js/login.js"></script>
</body>
</html>
