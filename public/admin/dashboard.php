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
$paymentsResult = $client->select(
    'payments',
    'select=*,students(name,enrollment),guardians(parent_name,email)&status=eq.paid&order=paid_at.desc&limit=200'
);
$payments = $paymentsResult['data'] ?? [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin - Entradas</title>
  <link rel="stylesheet" href="/assets/style.css?v=5" />
  <style>
    .admin-wrap{max-width:1120px;margin:0 auto;padding:28px 20px}
    .admin-header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
    .admin-title{font-weight:800;letter-spacing:.08em;font-size:12px;color:#1a2133}
    .admin-card{background:#fff;border-radius:16px;padding:18px;border:1px solid var(--line);box-shadow:var(--shadow-soft)}
    .admin-tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px}
    .admin-tab{background:#EFF3FA;border-color:#D5DCE8;color:#1a2133}
    .admin-card{background:#FDFDFE;border-radius:16px;padding:18px;border:1px solid #E6EAF2;box-shadow:var(--shadow-soft)}
    .admin-table{width:100%;border-collapse:collapse}
    .admin-table th,.admin-table td{padding:10px 8px}
    .admin-table tr{border-top:1px solid #f1f5f9}
    .charge-list{display:grid;gap:12px;margin-top:12px}
    .charge-item{background:#F6F8FC;border:1px solid #E6E9F2;border-radius:14px;padding:14px}
    .charge-header{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}
    .charge-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
    .date-list{display:grid;gap:8px}
    .date-row{display:flex;gap:8px;align-items:center}
    .date-row input{flex:1}
    .charge-message{margin-top:12px;font-size:13px}
    .hidden{display:none}
  </style>
</head>
<body>
  <div class="admin-wrap">
    <header class="admin-header">
      <div class="admin-title">DIARIAS VILLAGE • ADMIN</div>
      <div class="cta">
        <a class="btn btn-ghost btn-sm" href="/admin/import.php">Importar alunos</a>
        <a class="btn btn-ghost btn-sm" href="/logout.php">Sair</a>
      </div>
    </header>

    <div class="admin-card">
      <div class="admin-tabs">
        <button class="btn btn-primary btn-sm" type="button" data-tab="charges">Cobrança</button>
        <button class="btn btn-primary btn-sm" type="button" data-tab="entries">Entradas confirmadas</button>
      </div>

      <section id="tab-entries">
        <h2>Entradas confirmadas</h2>
        <p class="muted">Pagamentos confirmados e liberados para entrada.</p>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Aluno</th>
                <th>Matricula</th>
                <th>Pagamento</th>
                <th>Tipo</th>
                <th>Data do day-use</th>
                <th>Confirmado em</th>
                <th>Valor</th>
                <th>Codigo</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($payments)): ?>
                <tr>
                  <td colspan="8">Nenhuma entrada confirmada ainda.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                  <?php
                    $student = $payment['students'] ?? [];
                    $billing = $payment['billing_type'] === 'PIX' ? 'PIX' : 'Debito';
                    $dailyLabel = $payment['daily_type'] === 'emergencial' ? 'Emergencial' : 'Planejada';
                    $amount = number_format((float) $payment['amount'], 2, ',', '.');
                    $dayUse = date('d/m/Y', strtotime($payment['payment_date']));
                    $confirmed = $payment['paid_at'] ? date('d/m/Y H:i', strtotime($payment['paid_at'])) : '-';
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($student['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($student['enrollment'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo $billing; ?></td>
                    <td><?php echo $dailyLabel; ?></td>
                    <td><?php echo $dayUse; ?></td>
                    <td><?php echo $confirmed; ?></td>
                    <td>R$ <?php echo $amount; ?></td>
                    <td><?php echo htmlspecialchars($payment['access_code'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="tab-charges" class="hidden">
        <h2>Cobrança</h2>
        <p class="muted">Selecione alunos e informe os dados do responsável para enviar as cobranças em massa.</p>

        <div class="form-group">
          <label>Aluno</label>
          <input id="charge-student" list="students-list" placeholder="Digite o nome do aluno" autocomplete="off" />
          <datalist id="students-list"></datalist>
        </div>

        <div id="charge-list" class="charge-list"></div>

        <button class="btn btn-primary" id="send-charges" type="button">Enviar cobranças</button>
        <div id="charge-message" class="charge-message"></div>
      </section>
    </div>

    <div class="footer">Desenvolvido por Lucas Goncalves Junior - 2026</div>
  </div>

  <script src="/assets/js/admin-dashboard.js?v=3"></script>
</body>
</html>
