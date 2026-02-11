<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    header('Location: /admin/');
    exit;
}

$client = new SupabaseClient(new HttpClient());
$result = $client->select(
    'pendencia_de_cadastro',
    'select=id,student_name,guardian_name,guardian_cpf,guardian_email,created_at,paid_at&order=created_at.desc&limit=500'
);
$pendencias = $result['data'] ?? [];

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pendenciaId = trim($_POST['pendencia_id'] ?? '');
    if ($pendenciaId !== '') {
        $update = $client->update('pendencia_de_cadastro', 'id=eq.' . urlencode($pendenciaId), [
            'paid_at' => date('c'),
        ]);
        $message = $update['ok'] ? 'Baixa manual registrada.' : 'Falha ao dar baixa.';
        if ($update['ok']) {
            header('Location: /admin/settle-pendencia.php?ok=1');
            exit;
        }
    }
}

$ok = isset($_GET['ok']) && $_GET['ok'] === '1';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Baixa manual • Admin</title>
  <link rel="stylesheet" href="/assets/style.css?v=5" />
  <style>
    .settle-wrap { max-width: 900px; margin: 0 auto; padding: 24px 16px; }
    .settle-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
    .settle-table { width: 100%; border-collapse: collapse; }
    .settle-table th, .settle-table td { padding: 10px 8px; text-align: left; border-top: 1px solid #e2e8f0; }
    .btn-settle { background: #B91C1C; color: #fff; border: none; padding: 8px 14px; border-radius: 10px; font-weight: 700; cursor: pointer; }
    .btn-settle:hover { background: #991B1B; }
    .msg { padding: 12px; border-radius: 12px; margin-bottom: 16px; }
    .msg-ok { background: #D1FAE5; color: #065F46; }
    .msg-err { background: #FEE2E2; color: #991B1B; }
  </style>
</head>
<body>
  <div class="settle-wrap">
    <div class="settle-header">
      <h1>Baixa manual de pendências</h1>
      <div>
        <a href="/admin/dashboard.php" class="btn btn-ghost btn-sm">Voltar ao dashboard</a>
      </div>
    </div>

    <?php if ($ok): ?>
      <div class="msg msg-ok">Baixa manual registrada.</div>
    <?php endif; ?>
    <?php if ($message && !$ok): ?>
      <div class="msg msg-err"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <p class="muted">Pendências sem baixa. Marque como pago após conferir no Asaas.</p>

    <table class="settle-table">
      <thead>
        <tr>
          <th>Aluno</th>
          <th>Responsável</th>
          <th>CPF</th>
          <th>E-mail</th>
          <th>Registrado em</th>
          <th>Ação</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $pendentes = array_filter($pendencias, fn($p) => empty($p['paid_at']));
        if (empty($pendentes)):
        ?>
          <tr>
            <td colspan="6">Nenhuma pendência sem baixa.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($pendentes as $p): ?>
            <?php $created = $p['created_at'] ? date('d/m/Y H:i', strtotime($p['created_at'])) : '-'; ?>
            <tr>
              <td><?php echo htmlspecialchars($p['student_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($p['guardian_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($p['guardian_cpf'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($p['guardian_email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo $created; ?></td>
              <td>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="pendencia_id" value="<?php echo htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8'); ?>" />
                  <button type="submit" class="btn-settle" onclick="return confirm('Confirmar baixa manual?');">Dar baixa</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
