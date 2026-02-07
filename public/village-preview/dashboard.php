<?php
$bootstrapPaths = [
  __DIR__ . '/../src/Bootstrap.php',
  __DIR__ . '/../../src/Bootstrap.php',
];
foreach ($bootstrapPaths as $bootstrapPath) {
  if (file_exists($bootstrapPath)) {
    require_once $bootstrapPath;
    break;
  }
}

use App\Helpers;

$user = Helpers::requireAuthWeb();
$today = date('Y-m-d');
$hour = (int) date('H');
$minDate = $hour >= 16 ? date('Y-m-d', strtotime('+1 day')) : $today;
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
  <link rel="stylesheet" href="assets/style.css?v=4?v=finalv3">
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

        <aside class="hero-card" aria-label="Formulario de pagamento">
          <h3>Gerar pagamento</h3>
          <p class="muted">Preencha os dados abaixo.</p>

          <form id="payment-form">
            <div class="grid-2">
              <div class="form-group">
                <label>Data</label>
                <input type="date" id="payment-date" value="<?php echo $minDate; ?>" min="<?php echo $minDate; ?>" required />
                <div class="small">Após 16h, somente datas futuras.</div>
              </div>
              <div class="form-group">
                <label>Forma de pagamento</label>
                <select id="billing-type">
                  <option value="PIX">PIX</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>CPF/CNPJ do responsável</label>
              <input type="text" id="billing-document" placeholder="Digite o CPF ou CNPJ" required />
              <div class="small">Necessário para confirmar o pagamento no Asaas.</div>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Gerar pagamento</button>
            <div id="payment-message"></div>
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
            <p>Antes das 10h do day-use (R$ 77,00).</p>
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
    </div>
  </footer>

  <script src="/assets/js/dashboard.js"></script>
</body>
</html>
