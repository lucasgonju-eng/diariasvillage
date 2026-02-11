<?php
require_once __DIR__ . '/src/Bootstrap.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Primeiro acesso - Diarias Village</title>
  <meta name="description" content="Confirme o aluno, cadastre o e-mail e defina a senha para liberar o acesso." />
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
            <div class="brand-title">DIARIAS VILLAGE</div>
            <div class="brand-sub">Primeiro acesso e cadastro</div>
          </div>
        </div>

        <a class="btn btn-ghost btn-sm" href="/login.php">Entrar</a>
      </div>

      <div class="hero-grid">
        <div class="hero-left">
          <div class="pill">Primeiro acesso</div>
          <h1>Ative o acesso em poucos minutos.</h1>
          <p class="lead">
            Confirme o aluno, informe o CPF e o e-mail do responsavel e defina a senha
            para liberar o acesso.
          </p>

          <div class="microchips" role="list">
            <span class="microchip" role="listitem">Dados protegidos</span>
            <span class="microchip" role="listitem">CPF como acesso</span>
            <span class="microchip" role="listitem">Confirmacao via e-mail</span>
          </div>
        </div>

        <aside class="hero-card" aria-label="Cadastro do responsavel">
          <h3>Cadastro do responsavel</h3>
          <p class="muted">Complete os dados para continuar.</p>

          <form id="register-form">
            <div class="form-group">
              <label>Nome do aluno</label>
              <input id="student-name" placeholder="Digite o nome do aluno" required />
              <div class="small">Digite exatamente como no cadastro escolar.</div>
            </div>

            <div class="form-group">
              <label>CPF do responsavel</label>
              <input type="text" id="cpf" placeholder="000.000.000-00" required />
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

            <button class="btn btn-primary btn-block" type="submit">Primeiro acesso</button>
            <div id="form-message"></div>
          </form>

          <div class="small" style="margin-top:12px;">
            Problemas no cadastro? Garanta sua diária planejada clicando aqui.
            <button class="btn btn-primary btn-sm" id="open-pending" type="button">Abrir formulário</button>
          </div>

          <form id="pending-form" style="margin-top:12px;display:none;">
            <div class="form-group">
              <label>Nome do aluno</label>
              <input type="text" id="pending-student" required />
            </div>
            <div class="form-group">
              <label>Nome do responsável</label>
              <input type="text" id="pending-guardian" required />
            </div>
            <div class="form-group">
              <label>CPF do responsável</label>
              <input type="text" id="pending-cpf" required />
            </div>
            <div class="form-group">
              <label>E-mail do responsável (opcional)</label>
              <input type="email" id="pending-email" />
            </div>
            <button class="btn btn-primary btn-block" type="submit">Enviar pendência</button>
            <div id="pending-message"></div>
          </form>
        </aside>
      </div>
    </div>

    <svg class="wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
      <path d="M0,64 C240,120 480,120 720,72 C960,24 1200,24 1440,72 L1440,120 L0,120 Z"></path>
    </svg>
  </header>

  <main>
    <section class="section section-alt" id="como-funciona">
      <div class="container">
        <div class="section-head">
          <h2>Como funciona</h2>
          <p class="muted">Cadastro rapido, sem etapas desnecessarias.</p>
        </div>

        <div class="steps">
          <div class="step">
            <div class="step-n">1</div>
            <div>
              <div class="step-t">Encontre o aluno</div>
              <div class="muted">Digite o nome e selecione na lista.</div>
            </div>
          </div>
          <div class="step">
            <div class="step-n">2</div>
            <div>
              <div class="step-t">Informe CPF e e-mail</div>
              <div class="muted">Use os dados do responsavel.</div>
            </div>
          </div>
          <div class="step">
            <div class="step-n">3</div>
            <div>
              <div class="step-t">Defina a senha</div>
              <div class="muted">Pronto para acessar o sistema.</div>
            </div>
          </div>
        </div>

        <div class="final-cta">
          <div>
            <div class="final-title">Ja possui cadastro?</div>
            <div class="muted">Entre com seu e-mail e senha.</div>
          </div>
          <a class="btn btn-primary" href="/login.php">Entrar</a>
        </div>
      </div>
    </section>
  </main>

  <footer class="footer">
    <div class="container">
      Desenvolvido por Lucas Goncalves Junior - 2026
    </div>
  </footer>

  <script src="/assets/js/app.js"></script>
</body>
</html>
