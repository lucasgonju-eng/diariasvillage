<?php
require_once __DIR__ . '/src/Bootstrap.php';

use App\Helpers;

$user = Helpers::requireAuthWeb();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Perfil - Diárias Village</title>
  <meta name="description" content="Atualize os dados do responsável e mantenha o cadastro em dia." />
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
            <div class="brand-sub">Perfil do responsável</div>
          </div>
        </div>

        <div class="cta">
          <a class="btn btn-ghost btn-sm" href="/dashboard.php">Dashboard</a>
          <a class="btn btn-ghost btn-sm" href="/logout.php">Sair</a>
        </div>
      </div>

      <div class="hero-grid">
        <div class="hero-left">
          <div class="pill">Perfil</div>
          <h1>Atualize seus dados.</h1>
          <p class="lead">Mantenha o cadastro do responsável sempre atualizado.</p>

          <div class="microchips" role="list">
            <span class="microchip" role="listitem">Dados protegidos</span>
          <span class="microchip" role="listitem">Atualização rápida</span>
            <span class="microchip" role="listitem">Acesso seguro</span>
          </div>
        </div>

        <aside class="hero-card" aria-label="Formulario do perfil">
          <h3>Editar perfil</h3>
          <p class="muted">Atualize os dados do responsável.</p>

          <form id="profile-form">
            <div class="form-group">
              <label>Nome do responsável</label>
              <input id="parent-name" value="<?php echo htmlspecialchars($user['parent_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            </div>
            <div class="form-group">
              <label>Telefone</label>
              <input id="parent-phone" value="<?php echo htmlspecialchars($user['parent_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            </div>
            <div class="form-group">
              <label>CPF/CNPJ</label>
              <input id="parent-document" value="<?php echo htmlspecialchars($user['parent_document'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            </div>

            <div class="grid-2">
              <div class="form-group">
                <label>Nova senha</label>
                <input type="password" id="new-password" />
              </div>
              <div class="form-group">
                <label>Confirmar nova senha</label>
                <input type="password" id="new-password-confirm" />
              </div>
            </div>

            <button class="btn btn-primary btn-block" type="submit">Salvar</button>
            <div id="profile-message"></div>
          </form>
        </aside>
      </div>
    </div>

    <svg class="wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
      <path d="M0,64 C240,120 480,120 720,72 C960,24 1200,24 1440,72 L1440,120 L0,120 Z"></path>
    </svg>
  </header>

  <footer class="footer">
    <div class="container">
      Desenvolvido por Lucas Gonçalves Junior - 2026
      <a class="tinyLink" href="/admin/" aria-label="Acesso administrativo">Admin</a>
    </div>
  </footer>

  <script src="/assets/js/profile.js"></script>
</body>
</html>
