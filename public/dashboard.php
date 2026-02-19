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

$user = Helpers::requireAuthWeb();
$today = date('Y-m-d');
$hour = (int) date('H');
$minDate = $hour >= 16 ? date('Y-m-d', strtotime('+1 day')) : $today;
$dashboardError = isset($_SESSION['dashboard_error']) ? (string) $_SESSION['dashboard_error'] : '';
unset($_SESSION['dashboard_error']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard - Diárias Village</title>
  <meta name="description" content="Escolha a diária do dia com praticidade e gere o pagamento." />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/style.css?v=5">
</head>
<body>
  <header class="hero" id="top">
    <div class="container">
      <div class="topbar">
        <div class="brand">
          <span class="brand-mark" aria-hidden="true"></span>
          <div class="brand-text">
            <div class="brand-title">DIÁRIAS VILLAGE</div>
            <div class="brand-sub">Painel do responsável</div>
          </div>
        </div>

        <div class="cta">
          <a class="btn btn-ghost btn-sm" href="/profile.php">Perfil</a>
          <a class="btn btn-ghost btn-sm" href="/logout.php">Sair</a>
        </div>
      </div>

      <div class="hero-grid">
        <div class="hero-left">
          <div class="pill">Dashboard</div>
          <h1>Bem-vindo!</h1>
          <p class="lead">Escolha a diária do dia com praticidade.</p>

          <div class="microchips" role="list">
            <span class="microchip" role="listitem">Pagamento via PIX</span>
            <span class="microchip" role="listitem">Liberação automática</span>
            <span class="microchip" role="listitem">Confirmação por e-mail</span>
          </div>
        </div>

        <aside class="hero-card" aria-label="Formulário de início da diária">
          <h3>Montar grade da diária</h3>
          <p class="muted">Escolha a data e siga para a etapa de Grade de Oficina Modular.</p>
          <div class="info-note" id="planned-countdown" data-now="<?php echo date('c'); ?>">
            Carregando contagem regressiva da diária planejada...
          </div>

          <form id="payment-form" method="get" action="/api/diaria-iniciar.php">
            <div class="grid-2">
              <div class="form-group">
                <label>Data</label>
                <input type="date" id="payment-date" name="date" value="<?php echo $minDate; ?>" min="<?php echo $minDate; ?>" required />
                <div class="small">Após 16h, somente datas futuras.</div>
              </div>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Ir para Grade de Oficina Modular</button>
            <div id="payment-message"></div>
            <?php if ($dashboardError !== ''): ?>
              <div class="error" style="margin-top:8px;"><?php echo htmlspecialchars($dashboardError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
          </form>
        </aside>
      </div>
    </div>

    <svg class="wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
      <path d="M0,64 C240,120 480,120 720,72 C960,24 1200,24 1440,72 L1440,120 L0,120 Z"></path>
    </svg>
  </header>

  <main>
    <section class="section section-alt" id="info-diarias">
      <div class="container">
        <div class="section-head">
          <h2>Informações das diárias</h2>
          <p class="muted">Regras aplicadas automaticamente conforme o horário do pedido.</p>
        </div>

        <div class="info-cards">
          <div class="info-card">
            <h3>Diária Planejada</h3>
            <p>Antes das 10h do day-use (R$ 5,00 - valor temporário para testes).</p>
          </div>
          <div class="info-card">
            <h3>Diária Emergencial</h3>
            <p>Após as 10h do day-use (R$ 97,00).</p>
          </div>
        </div>

        <div class="info-note">
          Para datas futuras, a diária é planejada automaticamente. Após 16h, a compra para o dia atual é encerrada.
          Finalizando o pagamento, você recebe por e-mail o número de confirmação do day-use.
        </div>
      </div>
    </section>
  </main>

  <footer class="footer">
    <div class="container">
      Desenvolvido por Lucas Gonçalves Junior - 2026
      <a class="tinyLink" href="/admin/" aria-label="Acesso administrativo">Admin</a>
    </div>
  </footer>

  <script src="/assets/js/dashboard.js?v=20260219-1"></script>
  <script>
    (async function checkPendingPaymentAfterReturn() {
      let paymentId = '';
      try {
        paymentId = sessionStorage.getItem('pendingPaymentId') || '';
      } catch (e) {
        return;
      }
      if (!paymentId) return;

      try {
        const res = await fetch('/api/payment-status.php?paymentId=' + encodeURIComponent(paymentId), {
          method: 'GET',
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json' },
        });
        const data = await res.json().catch(() => ({}));
        if (res.ok && data.ok && data.payment && data.payment.status === 'paid') {
          const successUrl = (typeof data.redirect_to === 'string' && data.redirect_to)
            ? data.redirect_to
            : '/pagamento-sucesso.php?paymentId=' + encodeURIComponent(paymentId);
          try {
            sessionStorage.removeItem('pendingPaymentId');
            sessionStorage.removeItem('pendingPaymentSuccessUrl');
          } catch (e) {
            // Sem impacto no fluxo.
          }
          window.location.href = successUrl;
          return;
        }
      } catch (e) {
        // Não interrompe o uso normal do dashboard.
      }
    })();
  </script>
</body>
</html>
