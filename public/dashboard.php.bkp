<?php
require_once __DIR__ . '/src/Bootstrap.php';

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
  <title>Dashboard</title>
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">Diarias Village</div>
      <nav class="nav">
        <a class="button secondary" href="/profile.php">Perfil</a>
        <a class="button secondary" href="/logout.php">Sair</a>
      </nav>
    </header>

    <div class="card">
      <h2>Bem-vindo!</h2>
      <p class="subtitle">Escolha a diaria do dia com praticidade.</p>
      <div class="notice">
        Diaria Planejada: antes das 10h do day-use (R$ 77,00).<br />
        Diaria Emergencial: apos as 10h do day-use (R$ 97,00).
      </div>
      <p class="subtitle">
        Para datas futuras, a diaria e planejada automaticamente. Apos 16h, a compra para o dia atual e encerrada.
        Finalizando o pagamento, voce recebe por e-mail o numero de confirmacao do day-use.
      </p>
      <div class="construction"><span>🏗️</span>Estamos em construcao, mas estamos a todo vapor para a sua comodidade!</div>

      <form id="payment-form">
        <div class="grid-2">
          <div class="form-group">
            <label>Data</label>
            <input type="date" id="payment-date" value="<?php echo $minDate; ?>" min="<?php echo $minDate; ?>" required />
            <div class="small">Apos 16h, somente datas futuras.</div>
          </div>
          <div class="form-group">
            <label>Forma de pagamento</label>
            <select id="billing-type">
              <option value="PIX">PIX</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>CPF/CNPJ do responsavel</label>
          <input type="text" id="billing-document" placeholder="Digite o CPF ou CNPJ" required />
          <div class="small">Necessario para confirmar o pagamento no Asaas.</div>
        </div>
        <button class="button" type="submit">Gerar pagamento</button>
        <div id="payment-message"></div>
      </form>
    </div>
    <div class="footer">Desenvolvido por Lucas Goncalves Junior - 2026</div>
  </div>
  <script src="/assets/js/dashboard.js"></script>
</body>
</html>
