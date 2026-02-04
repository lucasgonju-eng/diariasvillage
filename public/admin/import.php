<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Env;

$key = $_GET['key'] ?? '';
$debug = ($_GET['debug'] ?? '') === '1';

if ($debug && $key === Env::get('ADMIN_SECRET', '')) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}
if ($key !== Env::get('ADMIN_SECRET', '')) {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Importar alunos</title>
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">Diarias Village</div>
    </header>

    <div class="card">
      <h2>Importar alunos</h2>
      <p class="subtitle">Envie CSV, XLS ou XLSX com colunas: nome, matricula, serie / turma, nascimento.</p>

      <form action="/api/import-students.php?key=<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" method="post" enctype="multipart/form-data">
        <div class="form-group">
          <input type="file" name="file" required />
        </div>
        <button class="button" type="submit">Importar</button>
      </form>
    </div>
  </div>
</body>
</html>
