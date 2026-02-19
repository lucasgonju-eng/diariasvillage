<?php
$bootstrapCandidates = [
    __DIR__ . '/src/Bootstrap.php',
    dirname(__DIR__) . '/src/Bootstrap.php',
];
foreach ($bootstrapCandidates as $bootstrapFile) {
    if (is_file($bootstrapFile)) {
        require_once $bootstrapFile;
        break;
    }
}

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

$user = Helpers::requireAuth();
$paymentId = isset($_GET['paymentId']) ? trim((string) $_GET['paymentId']) : '';
if ($paymentId === '') {
    header('Location: /dashboard.php');
    exit;
}

$client = new SupabaseClient(new HttpClient());
$result = $client->select(
    'payments',
    'select=id,status,paid_at,access_code,payment_date,daily_type,amount'
    . '&id=eq.' . rawurlencode($paymentId)
    . '&guardian_id=eq.' . rawurlencode((string) $user['id'])
    . '&limit=1'
);
if (!($result['ok'] ?? false) || empty($result['data'])) {
    header('Location: /dashboard.php');
    exit;
}

$payment = $result['data'][0];
$status = strtolower((string) ($payment['status'] ?? 'pending'));
$isPaid = $status === 'paid' || !empty($payment['paid_at']);
if (!$isPaid) {
    header('Location: /dashboard.php');
    exit;
}

$code = trim((string) ($payment['access_code'] ?? ''));
$codeValid = (bool) preg_match('/^\d{6}$/', $code);
$paymentDate = !empty($payment['payment_date']) ? date('d/m/Y', strtotime((string) $payment['payment_date'])) : '-';
$dailyTypeRaw = (string) ($payment['daily_type'] ?? '');
$dailyType = str_starts_with($dailyTypeRaw, 'emergencial') ? 'Emergencial' : 'Planejada';
$amount = number_format((float) ($payment['amount'] ?? 0), 2, ',', '.');
$paidAt = !empty($payment['paid_at']) ? date('d/m/Y H:i', strtotime((string) $payment['paid_at'])) : '-';
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pagamento confirmado • Diárias Village</title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background: linear-gradient(180deg, #eef3fb 0%, #e4ecf9 100%);
      color: #0b1020;
    }
    .wrap { min-height: 100vh; display: grid; place-items: center; padding: 20px; }
    .card {
      width: 100%;
      max-width: 740px;
      border: 1px solid #d8e2f1;
      border-radius: 18px;
      background: #fff;
      overflow: hidden;
      box-shadow: 0 14px 35px rgba(11, 16, 32, .12);
    }
    .head {
      padding: 26px 24px;
      background: radial-gradient(1100px 380px at 20% 0%, #163a7a 0%, #0a1b4d 42%, #081636 100%);
      color: #fff;
    }
    .head h1 { margin: 0; font-size: 28px; line-height: 1.2; }
    .head p { margin: 10px 0 0; opacity: .94; }
    .body { padding: 22px; }
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: 999px;
      background: #ddf5e6;
      border: 1px solid #9ad7b3;
      color: #0f5a2a;
      font-size: 13px;
      font-weight: 800;
    }
    .summary {
      margin-top: 16px;
      border-radius: 12px;
      background: #f7f9fd;
      border: 1px solid #e3eaf7;
      padding: 15px;
      font-size: 14px;
      line-height: 1.75;
    }
    .code {
      margin-top: 16px;
      border-radius: 12px;
      background: #f8fbff;
      border: 1px solid #d8e6f8;
      padding: 16px;
    }
    .code strong { font-size: 28px; letter-spacing: .14em; display: block; margin-top: 6px; }
    .code-status {
      margin-top: 8px;
      font-size: 13px;
      font-weight: 700;
      color: <?php echo $codeValid ? "'#0f5a2a'" : "'#8b2d1f'"; ?>;
    }
    .actions { margin-top: 20px; display: flex; flex-wrap: wrap; gap: 10px; }
    .btn {
      text-decoration: none;
      padding: 11px 14px;
      border-radius: 12px;
      font-weight: 700;
      font-size: 14px;
    }
    .btn-primary { background: #d6b25e; color: #0b1020; }
    .btn-secondary { background: #0a1b4d; color: #fff; }
  </style>
</head>
<body>
  <div class="wrap">
    <section class="card">
      <div class="head">
        <h1>Tudo pago, certinho. Obrigado! ✅</h1>
        <p>Pagamento confirmado com sucesso. A diária já está liberada no sistema.</p>
      </div>
      <div class="body">
        <span class="badge">Confirmação recebida no SaaS</span>

        <div class="summary">
          Data da diária: <b><?php echo htmlspecialchars($paymentDate, ENT_QUOTES, 'UTF-8'); ?></b><br>
          Tipo: <b><?php echo htmlspecialchars($dailyType, ENT_QUOTES, 'UTF-8'); ?></b><br>
          Valor pago: <b>R$ <?php echo htmlspecialchars($amount, ENT_QUOTES, 'UTF-8'); ?></b><br>
          Confirmado em: <b><?php echo htmlspecialchars($paidAt, ENT_QUOTES, 'UTF-8'); ?></b>
        </div>

        <div class="code">
          Validação do código de entrada:
          <strong><?php echo htmlspecialchars($code !== '' ? $code : 'PENDENTE', ENT_QUOTES, 'UTF-8'); ?></strong>
          <div class="code-status">
            <?php echo $codeValid ? 'Código válido para entrada.' : 'Código ainda em processamento. Atualize em instantes.'; ?>
          </div>
        </div>

        <div class="actions">
          <a class="btn btn-primary" href="/dashboard.php">Ir para o painel</a>
          <a class="btn btn-secondary" href="/dashboard.php">Acompanhar minhas diárias</a>
        </div>
      </div>
    </section>
  </div>
</body>
</html>
