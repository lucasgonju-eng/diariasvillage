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
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">Diarias Village</div>
      <nav class="nav">
        <a class="button secondary" href="/admin/import.php">Importar alunos</a>
        <a class="button secondary" href="/logout.php">Sair</a>
      </nav>
    </header>

    <div class="card">
      <h2>Entradas confirmadas</h2>
      <p class="subtitle">Pagamentos confirmados e liberados para entrada.</p>

      <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse: collapse;">
          <thead>
            <tr style="text-align:left;">
              <th style="padding: 10px 8px;">Aluno</th>
              <th style="padding: 10px 8px;">Matricula</th>
              <th style="padding: 10px 8px;">Pagamento</th>
              <th style="padding: 10px 8px;">Tipo</th>
              <th style="padding: 10px 8px;">Data do day-use</th>
              <th style="padding: 10px 8px;">Confirmado em</th>
              <th style="padding: 10px 8px;">Valor</th>
              <th style="padding: 10px 8px;">Codigo</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($payments)): ?>
              <tr>
                <td colspan="8" style="padding: 12px 8px;">Nenhuma entrada confirmada ainda.</td>
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
                <tr style="border-top: 1px solid #f1f5f9;">
                  <td style="padding: 10px 8px;"><?php echo htmlspecialchars($student['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td style="padding: 10px 8px;"><?php echo htmlspecialchars($student['enrollment'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td style="padding: 10px 8px;"><?php echo $billing; ?></td>
                  <td style="padding: 10px 8px;"><?php echo $dailyLabel; ?></td>
                  <td style="padding: 10px 8px;"><?php echo $dayUse; ?></td>
                  <td style="padding: 10px 8px;"><?php echo $confirmed; ?></td>
                  <td style="padding: 10px 8px;">R$ <?php echo $amount; ?></td>
                  <td style="padding: 10px 8px;"><?php echo htmlspecialchars($payment['access_code'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="footer">Desenvolvido por Lucas Goncalves Junior - 2026</div>
  </div>
</body>
</html>
