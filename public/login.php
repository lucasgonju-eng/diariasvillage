<?php
require_once __DIR__ . '/src/Bootstrap.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Entrar - Diárias Village</title>
  <meta name="description" content="Acesse com seu e-mail verificado para continuar." />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/style.css?v=5" />
</head>
<body>
  <header class="hero" id="top">
    <div class="container">
      <div class="topbar">
        <div class="brand">
          <span class="brand-mark" aria-hidden="true"></span>
          <div class="brand-text">
            <div class="brand-title">DIÁRIAS VILLAGE</div>
            <div class="brand-sub">Acesso do responsável</div>
          </div>
        </div>

        <a class="btn btn-ghost btn-sm" href="/">Voltar</a>
      </div>

      <div class="hero-grid">
        <div class="hero-left">
          <div class="pill">Entrar</div>
          <h1>Bem-vindo de volta.</h1>
          <p class="lead">
            Use o CPF do responsável e a senha para acessar o sistema e
            concluir o pagamento.
          </p>

          <div class="microchips" role="list">
            <span class="microchip" role="listitem">Acesso seguro</span>
            <span class="microchip" role="listitem">Confirmação rápida</span>
            <span class="microchip" role="listitem">Sem burocracia</span>
          </div>
        </div>

        <aside class="hero-card" aria-label="Formulario de acesso">
          <h3>Entrar</h3>
          <p class="muted">Acesse com o CPF do responsável.</p>

          <form id="login-form">
            <div class="form-group">
              <label>CPF do responsável</label>
              <input type="text" id="login-cpf" inputmode="numeric" required />
            </div>
            <div class="form-group">
              <label>Senha</label>
              <input type="password" id="login-password" required />
            </div>
            <button class="btn btn-primary btn-block" type="submit">Entrar</button>
            <div id="login-message"></div>
          </form>
        </aside>
      </div>
    </div>

    <svg class="wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
      <path d="M0,64 C240,120 480,120 720,72 C960,24 1200,24 1440,72 L1440,120 L0,120 Z"></path>
    </svg>
  </header>

  <main>
    <section class="section section-alt" id="ajuda">
      <div class="container">
        <div class="section-head">
          <h2>Precisa de ajuda?</h2>
          <p class="muted">Se for seu primeiro acesso, crie o cadastro.</p>
        </div>

        <div class="final-cta">
          <div>
            <div class="final-title">Primeiro acesso</div>
            <div class="muted">Cadastre o e-mail e defina sua senha.</div>
          </div>
          <a class="btn btn-primary" href="/primeiro-acesso.php">Criar acesso</a>
        </div>
      </div>
    </section>
  </main>

  <footer class="footer">
    <div class="container">
      Desenvolvido por Lucas Gonçalves Junior - 2026
    </div>
  </footer>
  <script src="/assets/js/login.js?v=2"></script>
</body>
</html>
