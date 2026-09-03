<?php
require_once __DIR__ . '/src/Bootstrap.php';

use App\AdminAuth;
use App\Helpers;

$requestedContext = strtolower(trim((string) ($_REQUEST['context'] ?? '')));
$context = in_array($requestedContext, ['user', 'admin'], true)
    ? $requestedContext
    : (isset($_SESSION['user']) ? 'user' : 'admin');
$returnToMobile = (string) ($_REQUEST['return'] ?? '') === 'mobile';
$csrfKey = 'logout_csrf_' . $context;
$csrfToken = trim((string) ($_SESSION[$csrfKey] ?? ''));
if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION[$csrfKey] = $csrfToken;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $providedToken = trim((string) ($_POST['csrf_token'] ?? ''));
    if ($providedToken === '' || !hash_equals($csrfToken, $providedToken)) {
        http_response_code(403);
        exit('Solicitação expirada. Recarregue a página.');
    }
    unset($_SESSION[$csrfKey]);

    if ($context === 'admin') {
        (new AdminAuth())->logout();
        if (isset($_SESSION['admin_impersonating_student_id'])) {
            Helpers::clearUserSession();
        }
        foreach ([
            'admin_csrf_token',
            'admin_impersonating_student',
            'admin_impersonating_student_id',
            'admin_impersonating_guardian_id',
        ] as $key) {
            unset($_SESSION[$key]);
        }
        header('Location: /admin/');
        exit;
    }

    Helpers::clearUserSession();
    foreach ([
        'admin_impersonating_student',
        'admin_impersonating_student_id',
        'admin_impersonating_guardian_id',
    ] as $key) {
        unset($_SESSION[$key]);
    }
    session_regenerate_id(true);
    if (!empty($_SESSION['admin_id'])) {
        header('Location: /admin/dashboard.php?tab=entries');
    } else {
        header('Location: ' . ($returnToMobile ? '/mobile/?r=login' : '/'));
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    exit('Método inválido.');
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sair • Diárias Village</title>
  <link rel="stylesheet" href="/assets/style.css?v=5" />
</head>
<body>
  <main class="container" style="max-width:560px;padding-top:64px;">
    <section class="card">
      <h1>Sair da conta?</h1>
      <p>Confirme para encerrar somente a sessão <?php echo $context === 'admin' ? 'administrativa' : 'do responsável'; ?>.</p>
      <form method="post">
        <input type="hidden" name="context" value="<?php echo htmlspecialchars($context, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="return" value="<?php echo $returnToMobile ? 'mobile' : ''; ?>" />
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <button class="btn btn-primary" type="submit">Confirmar saída</button>
        <a class="btn btn-ghost" href="<?php echo $context === 'admin' ? '/admin/dashboard.php' : ($returnToMobile ? '/mobile/?r=grade' : '/dashboard.php'); ?>">Cancelar</a>
      </form>
    </section>
  </main>
</body>
</html>
