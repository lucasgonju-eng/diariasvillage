<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Helpers;

$user = Helpers::requireAuthWeb();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Perfil</title>
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">Diarias Village</div>
      <nav class="nav">
        <a class="button secondary" href="/dashboard.php">Dashboard</a>
        <a class="button secondary" href="/logout.php">Sair</a>
      </nav>
    </header>

    <div class="card">
      <h2>Perfil</h2>
      <p class="subtitle">Atualize os dados do responsavel.</p>
      <form id="profile-form">
        <div class="form-group">
          <label>Nome do responsavel</label>
          <input id="parent-name" value="<?php echo htmlspecialchars($user['parent_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
        </div>
        <div class="form-group">
          <label>Telefone</label>
          <input id="parent-phone" value="<?php echo htmlspecialchars($user['parent_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
        </div>
        <div class="form-group">
          <label>Documento</label>
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

        <button class="button" type="submit">Salvar</button>
        <div id="profile-message"></div>
      </form>
    </div>
  </div>
  <script src="/assets/js/profile.js"></script>
</body>
</html>
