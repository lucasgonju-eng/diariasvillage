<?php
$bootstrapCandidates = [
    __DIR__ . '/../src/Bootstrap.php',
    dirname(__DIR__, 2) . '/src/Bootstrap.php',
];
foreach ($bootstrapCandidates as $bootstrapFile) {
    if (is_file($bootstrapFile)) {
        require_once $bootstrapFile;
        break;
    }
}

use App\AdminAuth;
use App\LoginThrottle;

$error = '';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$adminAuth = new AdminAuth();
$adminAuth->bootstrapFromEnvironment();
$currentAdmin = $adminAuth->currentSession();
$adminLoginCsrf = trim((string) ($_SESSION['admin_login_csrf'] ?? ''));
if ($adminLoginCsrf === '') {
    $adminLoginCsrf = bin2hex(random_bytes(32));
    $_SESSION['admin_login_csrf'] = $adminLoginCsrf;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = (string) ($_POST['admin_login_user'] ?? ($_POST['username'] ?? ''));
    $passwordInput = (string) ($_POST['admin_login_pass'] ?? ($_POST['password'] ?? ''));
    $submittedCsrf = trim((string) ($_POST['csrf_token'] ?? ''));
    if ($submittedCsrf === '' || !hash_equals($adminLoginCsrf, $submittedCsrf)) {
        http_response_code(403);
        $error = 'Formulário expirado. Recarregue a página.';
    } else {
        $throttle = new LoginThrottle();
        $claim = $throttle->claim('admin', $usernameInput);
        if (!($claim['ok'] ?? false)) {
            http_response_code(503);
            $error = 'Login temporariamente indisponível. Tente novamente.';
        } elseif (!($claim['allowed'] ?? false)) {
            $retryAfter = max(1, (int) ($claim['retry_after'] ?? 60));
            http_response_code(429);
            header('Retry-After: ' . $retryAfter);
            $error = 'Muitas tentativas. Aguarde alguns minutos antes de tentar novamente.';
        } else {
            $login = $adminAuth->login($usernameInput, $passwordInput);
            if ($login['ok']) {
                if (!$throttle->clearAfterSuccess()) {
                    error_log('[admin-login] falha ao limpar contadores após autenticação válida');
                }
                unset($_SESSION['admin_login_csrf']);
                header('Location: /admin/dashboard.php?tab=entries');
                exit;
            }
            $error = (string) ($login['error'] ?? 'Usuário ou senha inválidos.');
        }
    }
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
      <?php if ($currentAdmin !== null): ?>
        <p class="subtitle">Escolha uma opção abaixo.</p>
        <div class="nav">
          <a class="button" href="/admin/dashboard.php?tab=entries">Entradas confirmadas</a>
          <?php if (($currentAdmin['role'] ?? '') === AdminAuth::ROLE_ADMIN): ?>
            <a class="button secondary" href="/admin/import.php">Importar alunos</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <p class="subtitle">Informe usuário e senha para continuar.</p>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($adminLoginCsrf, ENT_QUOTES, 'UTF-8'); ?>" />
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
