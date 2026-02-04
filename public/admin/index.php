<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Env;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');
    if ($password !== '' && $password === Env::get('ADMIN_SECRET', '')) {
        $_SESSION['admin_authenticated'] = true;
        header('Location: /admin/dashboard.php');
        exit;
    }
    $error = 'Senha invalida.';
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
      <div class="logo">Diarias Village</div>
    </header>

    <div class="card">
      <h2>Painel administrativo</h2>
      <?php if (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true): ?>
        <p class="subtitle">Escolha uma opcao abaixo.</p>
        <div class="nav">
          <a class="button" href="/admin/dashboard.php">Entradas confirmadas</a>
          <a class="button secondary" href="/admin/import.php">Importar alunos</a>
        </div>
      <?php else: ?>
        <p class="subtitle">Digite a senha para continuar.</p>
        <form method="post">
          <div class="form-group">
            <label>Senha</label>
            <input type="password" name="password" required />
          </div>
          <button class="button" type="submit">Entrar</button>
          <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>
        </form>
      <?php endif; ?>
    </div>
    <div class="footer">Desenvolvido por Lucas Goncalves Junior - 2026</div>
  </div>
</body>
</html>
