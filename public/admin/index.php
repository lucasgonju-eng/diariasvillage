<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Env;

$error = '';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = (string) ($_POST['admin_login_user'] ?? ($_POST['username'] ?? ''));
    $passwordInput = (string) ($_POST['admin_login_pass'] ?? ($_POST['password'] ?? ''));
    $username = strtolower(trim($usernameInput));
    $password = trim($passwordInput);
    $adminSecret = Env::get('ADMIN_SECRET', '');

    $isAdmin = $username === 'admin' && $password !== '' && $password === $adminSecret;
    $isSecretaria = $username === 'secretaria' && $password === 'Ei32743176';

    if ($isAdmin || $isSecretaria) {
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_user'] = $username;
        header('Location: /admin/dashboard.php?tab=entries');
        exit;
    }
    $error = 'Usuário ou senha inválidos.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin</title>
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">Diárias Village</div>
    </header>

    <div class="card">
      <h2>Painel administrativo</h2>
      <?php if (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true): ?>
        <p class="subtitle">Escolha uma opção abaixo.</p>
        <div class="nav">
          <a class="button" href="/admin/dashboard.php?tab=entries">Entradas confirmadas</a>
          <a class="button secondary" href="/admin/import.php">Importar alunos</a>
        </div>
      <?php else: ?>
        <p class="subtitle">Informe usuário e senha para continuar.</p>
        <form method="post">
          <input
            type="text"
            name="username"
            autocomplete="username"
            tabindex="-1"
            aria-hidden="true"
            style="position:absolute;left:-10000px;opacity:0;width:1px;height:1px;"
          />
          <input
            type="password"
            name="password"
            autocomplete="current-password"
            tabindex="-1"
            aria-hidden="true"
            style="position:absolute;left:-10000px;opacity:0;width:1px;height:1px;"
          />
          <div class="form-group">
            <label>Usuário</label>
            <input
              id="admin-login-user"
              type="text"
              name="admin_login_user"
              autocomplete="off"
              autocapitalize="none"
              spellcheck="false"
              data-lpignore="true"
              required
            />
          </div>
          <div class="form-group">
            <label>Senha</label>
            <input
              id="admin-login-pass"
              type="password"
              name="admin_login_pass"
              autocomplete="new-password"
              data-lpignore="true"
              required
            />
          </div>
          <button class="button" type="submit">Entrar</button>
          <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>
        </form>
      <?php endif; ?>
    </div>
    <div class="footer">Desenvolvido por Lucas Gonçalves Junior - 2026</div>
  </div>
  <script>
    (function () {
      const userInput = document.getElementById('admin-login-user');
      const passInput = document.getElementById('admin-login-pass');
      if (!userInput) return;

      const cpfLike = /^\d{3}\.?\d{3}\.?\d{3}-?\d{2}$/;
      const clearAutofillNoise = () => {
        const value = (userInput.value || '').trim();
        if (value !== '' && cpfLike.test(value)) {
          userInput.value = '';
          if (passInput) passInput.value = '';
        }
      };

      setTimeout(clearAutofillNoise, 0);
      setTimeout(clearAutofillNoise, 250);
    })();
  </script>
</body>
</html>
