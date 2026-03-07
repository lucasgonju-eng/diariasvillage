<?php
require_once __DIR__ . '/../src/Bootstrap.php';
$debug = ($_GET['debug'] ?? '') === '1';

$sessionOk = isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;

if ($debug && $sessionOk) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

if (!$sessionOk) {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
}
$success = ($_GET['success'] ?? '') === '1';
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
      <div class="logo">Diárias Village</div>
      <nav class="nav">
        <a class="button secondary" href="/admin/dashboard.php?tab=entries">Entradas</a>
        <a class="button secondary" href="/logout.php">Sair</a>
      </nav>
    </header>

    <div class="card">
      <h2>Importar alunos</h2>
      <p class="subtitle">Envie CSV, XLS ou XLSX com colunas: nome, matrícula, série / turma, nascimento.</p>
      <?php if ($success): ?>
        <div class="success">Importação concluída com sucesso.</div>
      <?php endif; ?>

      <form action="/api/import-students.php" method="post" enctype="multipart/form-data">
        <div class="form-group">
          <input type="file" name="file" required />
        </div>
        <button class="button" type="submit">Importar</button>
      </form>
    </div>

    <div class="card" style="margin-top:18px;">
      <h2>Importar responsáveis</h2>
      <p class="subtitle">Envie PDF ou JSON com colunas: student_name, guardian_name, guardian_email, guardian_phone, guardian_cpf.</p>

      <form action="/api/import-guardians.php?return=html" method="post" enctype="multipart/form-data">
        <div class="form-group">
          <label for="guardians-file">Arquivo (PDF ou JSON)</label>
          <input id="guardians-file" type="file" name="file" accept=".pdf,.json" />
        </div>
        <div class="form-group">
          <label for="guardians-json">Ou cole o JSON abaixo</label>
          <textarea id="guardians-json" name="json" rows="6" placeholder='[{"student_name":"ALUNO","guardian_name":"RESPONSAVEL","guardian_email":"email@exemplo.com","guardian_phone":"6299999999","guardian_cpf":"12345678900"}]'></textarea>
        </div>
        <button class="button" type="submit">Importar responsáveis</button>
      </form>
    </div>
    <div class="footer">Desenvolvido por Lucas Gonçalves Junior - 2026</div>
  </div>
</body>
</html>
