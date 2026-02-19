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
date_default_timezone_set('America/Sao_Paulo');

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

$user = Helpers::requireAuthWeb();
$diariaId = isset($_GET['diariaId']) ? trim((string) $_GET['diariaId']) : '';
$invoiceUrl = isset($_GET['invoiceUrl']) ? trim((string) $_GET['invoiceUrl']) : '';
if ($diariaId === '') {
    header('Location: /dashboard.php');
    exit;
}

$client = new SupabaseClient(new HttpClient());
$diariaResult = $client->select(
    'diaria',
    'select=id,data_diaria,status_pagamento'
    . '&id=eq.' . rawurlencode($diariaId)
    . '&guardian_id=eq.' . rawurlencode((string) $user['id'])
    . '&limit=1'
);
if (!($diariaResult['ok'] ?? false) || empty($diariaResult['data'])) {
    header('Location: /dashboard.php');
    exit;
}
$diaria = $diariaResult['data'][0];
$dataDiaria = !empty($diaria['data_diaria']) ? date('d/m/Y', strtotime((string) $diaria['data_diaria'])) : '-';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Confirmando pagamento • Diárias Village</title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background: linear-gradient(180deg, #eef3fb 0%, #e4ecf9 100%);
      color: #0b1020;
    }
    .wrap { min-height: 100vh; display: grid; place-items: center; padding: 18px; }
    .card {
      width: 100%;
      max-width: 700px;
      background: #fff;
      border: 1px solid #d8e2f1;
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 14px 35px rgba(11, 16, 32, .12);
    }
    .head {
      padding: 24px;
      background: radial-gradient(1000px 320px at 20% 0%, #163a7a 0%, #0a1b4d 45%, #081636 100%);
      color: #fff;
    }
    .head h1 { margin: 0; font-size: 30px; line-height: 1.2; }
    .head p { margin: 8px 0 0; opacity: .95; }
    .body { padding: 22px; }
    .status {
      display: inline-block;
      padding: 8px 12px;
      border-radius: 999px;
      font-weight: 800;
      font-size: 13px;
      background: #fff4cc;
      border: 1px solid #f2dc8b;
      color: #6b4e00;
    }
    .summary {
      margin-top: 16px;
      border-radius: 12px;
      background: #f7f9fd;
      border: 1px solid #e3eaf7;
      padding: 14px;
      line-height: 1.8;
      font-size: 15px;
    }
    .hint {
      margin-top: 14px;
      color: #51607a;
      line-height: 1.6;
      font-size: 14px;
    }
    .actions { margin-top: 18px; display: flex; gap: 10px; flex-wrap: wrap; }
    .btn {
      border: 0;
      border-radius: 12px;
      padding: 11px 14px;
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
    }
    .btn-primary { background: #0a1b4d; color: #fff; }
    .btn-secondary { background: #d6b25e; color: #0b1020; text-decoration: none; display: inline-block; }
  </style>
</head>
<body>
  <div class="wrap">
    <section class="card">
      <div class="head">
        <h1>Confirmando pagamento...</h1>
        <p>Estamos validando a confirmação do Asaas para liberar sua diária automaticamente.</p>
      </div>
      <div class="body">
        <span id="status-badge" class="status">Aguardando confirmação</span>
        <div class="summary">
          Data da diária: <b><?php echo htmlspecialchars($dataDiaria, ENT_QUOTES, 'UTF-8'); ?></b><br>
          Assim que o status ficar pago, você será redirecionado para a tela de confirmação.
        </div>
        <div id="status-hint" class="hint">
          Pode levar alguns segundos. Se necessário, toque em "Verificar agora".
        </div>
        <div class="actions">
          <button id="btn-check" class="btn btn-primary" type="button">Verificar agora</button>
          <?php if ($invoiceUrl !== '' && $invoiceUrl !== '#'): ?>
            <a class="btn btn-secondary" href="<?php echo htmlspecialchars($invoiceUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
              Abrir pagamento no Asaas
            </a>
          <?php endif; ?>
          <a class="btn btn-secondary" href="/dashboard.php">Voltar ao painel</a>
        </div>
      </div>
    </section>
  </div>
  <script>
    const diariaId = <?php echo json_encode($diariaId, JSON_UNESCAPED_UNICODE); ?>;
    const statusBadge = document.getElementById('status-badge');
    const statusHint = document.getElementById('status-hint');
    const btnCheck = document.getElementById('btn-check');
    let checking = false;
    let tries = 0;
    const maxTries = 30; // ~60s com intervalo de 2s.

    async function checkStatus() {
      if (checking) return;
      checking = true;
      tries += 1;
      try {
        const res = await fetch('/api/payment-status.php?diariaId=' + encodeURIComponent(diariaId), {
          method: 'GET',
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json' },
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) {
          statusHint.textContent = (data && data.error)
            ? data.error
            : 'Não foi possível validar agora. Tente novamente em alguns segundos.';
          return;
        }
        if (data.payment && data.payment.status === 'paid' && data.redirect_to) {
          statusBadge.textContent = 'Pagamento confirmado';
          statusBadge.style.background = '#ddf5e6';
          statusBadge.style.borderColor = '#9ad7b3';
          statusBadge.style.color = '#0f5a2a';
          statusHint.textContent = 'Pagamento confirmado! Redirecionando...';
          window.location.href = data.redirect_to;
          return;
        }
        if (tries >= maxTries) {
          statusHint.textContent = 'Ainda sem confirmação automática. Toque em "Verificar agora" ou volte ao painel e tente novamente em instantes.';
        }
      } finally {
        checking = false;
      }
    }

    btnCheck.addEventListener('click', checkStatus);
    const timer = setInterval(() => {
      if (tries >= maxTries) {
        clearInterval(timer);
        return;
      }
      checkStatus();
    }, 2000);

    checkStatus();
  </script>
</body>
</html>
