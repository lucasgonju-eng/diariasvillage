<?php
/**
 * Landing Page - Diarias Village (isolada)
 * Ajuste somente o $continueUrl se necessario.
 */
$continueUrl = '/primeiro-acesso.php';
$brand = [
  'name' => 'Diarias Village',
  'tag'  => 'Pagamento rapido do Day use Village',
];

$prices = [
  'planned' => ['label' => 'Planejada', 'price' => 'R$ 77,00', 'rule' => 'Para usar hoje: pague ate 10h'],
  'urgent'  => ['label' => 'Emergencial', 'price' => 'R$ 97,00', 'rule' => 'Para usar hoje: apos 10h'],
];

date_default_timezone_set('America/Sao_Paulo');
$hour = (int) date('G');
$timeHint = '';
if ($hour >= 16) {
  $timeHint = 'Apos 16h, so e possivel comprar para uma data futura.';
} elseif ($hour >= 10) {
  $timeHint = 'Para hoje, agora a compra entra como Emergencial.';
} else {
  $timeHint = 'Para hoje, ainda da para Planejada ate 10h.';
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="description" content="Pague o Day use Village em minutos. Sem fila, sem burocracia. PIX e liberacao automatica.">
  <style>
    :root{
      --bg:#0B1020;
      --primary:#0A1B4D;
      --primary-2:#0F2A75;
      --text:#0B1020;
      --muted:#556070;
      --surface:#FFFFFF;
      --line:#E6E9EF;
      --accent:#D6B25E;
      --radius:16px;
      --shadow: 0 10px 30px rgba(11,16,32,.12);
      --max: 1120px;
      --font: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Helvetica Neue", Helvetica, sans-serif;
    }

    *{box-sizing:border-box}
    html,body{margin:0;padding:0;font-family:var(--font);color:var(--text);background:#F6F7FA}
    a{color:inherit;text-decoration:none}
    .container{max-width:var(--max);margin:0 auto;padding:28px 20px}

    .topbar{
      background:linear-gradient(180deg,var(--primary),var(--bg));
      color:#fff;
      padding:18px 0;
    }
    .header{
      display:flex;align-items:center;justify-content:space-between;gap:16px;
    }
    .brand{
      display:flex;align-items:center;gap:12px;
    }
    .brand img{height:38px;width:auto}
    .brand .title{
      display:flex;flex-direction:column;line-height:1.1;
    }
    .brand .title strong{font-size:14px;letter-spacing:.08em;text-transform:uppercase;opacity:.9}
    .brand .title span{font-size:12px;opacity:.85}

    .nav a{
      padding:10px 12px;border-radius:12px;opacity:.92
    }
    .nav a:hover{background:rgba(255,255,255,.08)}

    .hero{
      padding:42px 0 10px;
      color:#fff;
    }
    .hero-grid{
      display:grid;grid-template-columns:1.2fr .8fr;gap:28px;align-items:stretch;
    }
    .card{
      background:var(--surface);
      border:1px solid var(--line);
      border-radius:var(--radius);
      box-shadow:var(--shadow);
    }
    .hero-card{
      padding:28px;
    }
    .kicker{
      display:inline-flex;align-items:center;gap:8px;
      padding:8px 12px;border-radius:999px;
      background:rgba(214,178,94,.12);
      border:1px solid rgba(214,178,94,.35);
      color:#fff;
      font-size:12px;
    }
    h1{
      margin:14px 0 10px;
      font-size:42px;
      letter-spacing:-.02em;
    }
    .lead{
      margin:0 0 18px;
      font-size:16px;
      color:rgba(255,255,255,.88);
      line-height:1.6;
      max-width:62ch;
    }
    .actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px}
    .btn{
      display:inline-flex;align-items:center;justify-content:center;
      gap:10px;
      padding:12px 16px;
      border-radius:14px;
      font-weight:650;
      border:1px solid transparent;
      cursor:pointer;
    }
    .btn-primary{
      background:var(--accent);
      color:#1a1406;
      box-shadow:0 10px 20px rgba(214,178,94,.25);
    }
    .btn-primary:hover{filter:brightness(.98)}
    .btn-ghost{
      background:transparent;
      border-color:rgba(255,255,255,.25);
      color:#fff;
    }
    .btn-ghost:hover{background:rgba(255,255,255,.08)}

    .panel{
      padding:22px;
    }
    .panel h3{margin:0 0 10px;font-size:16px}
    .badges{display:flex;flex-wrap:wrap;gap:10px;margin-top:10px}
    .badge{
      display:inline-flex;align-items:center;gap:8px;
      padding:10px 12px;border-radius:999px;
      border:1px solid var(--line);
      background:#fff;
      color:var(--muted);
      font-size:13px;
    }
    .badge b{color:var(--text)}

    .section{padding:34px 0}
    .section h2{margin:0 0 10px;font-size:22px}
    .section p{margin:0;color:var(--muted);line-height:1.7}

    .grid-3{
      display:grid;
      grid-template-columns:repeat(3,1fr);
      gap:16px;
      margin-top:14px;
    }
    .info{
      padding:18px;
    }
    .info h4{margin:0 0 8px;font-size:15px}
    .info p{margin:0;color:var(--muted);font-size:14px;line-height:1.6}

    .steps{
      margin-top:16px;
      display:grid;
      grid-template-columns:repeat(4,1fr);
      gap:12px;
    }
    .step{
      padding:16px;
      border-radius:14px;
      border:1px solid var(--line);
      background:#fff;
    }
    .step .n{
      width:34px;height:34px;border-radius:10px;
      display:flex;align-items:center;justify-content:center;
      background:rgba(10,27,77,.08);
      border:1px solid rgba(10,27,77,.18);
      color:var(--primary);
      font-weight:800;
    }
    .step h5{margin:10px 0 6px;font-size:14px}
    .step p{margin:0;color:var(--muted);font-size:13px;line-height:1.55}

    .footer{
      padding:22px 0;
      color:rgba(255,255,255,.75);
      background:var(--bg);
    }
    .footer small{display:block;line-height:1.6}

    .form{
      padding:22px;
    }
    label{display:block;font-weight:650;font-size:13px;margin:14px 0 6px}
    input,select{
      width:100%;
      padding:12px 12px;
      border-radius:12px;
      border:1px solid var(--line);
      outline:none;
      font-size:14px;
    }
    input:focus,select:focus{border-color:rgba(10,27,77,.45);box-shadow:0 0 0 4px rgba(10,27,77,.08)}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .help{color:var(--muted);font-size:12px;margin-top:6px}
    .hr{height:1px;background:var(--line);margin:18px 0}

    .copybox{
      padding:14px;
      border-radius:14px;
      border:1px dashed rgba(10,27,77,.35);
      background:rgba(10,27,77,.04);
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
      font-size:13px;
      overflow:auto;
    }

    @media (max-width: 920px){
      .hero-grid{grid-template-columns:1fr}
      h1{font-size:34px}
      .grid-3{grid-template-columns:1fr}
      .steps{grid-template-columns:1fr 1fr}
    }
    @media (max-width: 560px){
      .steps{grid-template-columns:1fr}
      .row{grid-template-columns:1fr}
    }
  </style>
</head>

<body>
  <div class="topbar">
    <div class="container header">
      <div class="brand">
        <div class="title">
          <strong><?php echo htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
          <span><?php echo htmlspecialchars($brand['tag'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
      </div>
      <nav class="nav">
        <a href="<?php echo htmlspecialchars($continueUrl, ENT_QUOTES, 'UTF-8'); ?>">Continuar</a>
      </nav>
    </div>
  </div>

  <section class="hero">
    <div class="container hero-grid">
      <div class="card hero-card">
        <div class="kicker">🚧 Estamos aprimorando a plataforma. Obrigado por apoiar — tudo aqui e feito para sua comodidade.</div>
        <h1>Resolve em minutos.</h1>
        <p class="lead">
          Escolha o aluno, selecione a data, pague via PIX e pronto:
          a liberacao acontece automaticamente.
        </p>
        <div class="actions">
          <a class="btn btn-primary" href="<?php echo htmlspecialchars($continueUrl, ENT_QUOTES, 'UTF-8'); ?>">Continuar</a>
          <button class="btn btn-ghost" type="button" onclick="openRules()">Ver regras</button>
        </div>
        <div class="badges">
          <span class="badge"><b>Confirmacao por e-mail</b></span>
          <span class="badge"><b>Processo seguro</b></span>
          <span class="badge"><b>Secretaria avisada automaticamente</b></span>
        </div>
      </div>

      <div class="card panel">
        <h3>Voce so precisa fazer isso</h3>
        <p class="help"><?php echo htmlspecialchars($timeHint, ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="steps">
          <div class="step">
            <div class="n">1</div>
            <h5>Escolher o aluno</h5>
            <p>Lista oficial importada pela escola.</p>
          </div>
          <div class="step">
            <div class="n">2</div>
            <h5>Selecionar a data</h5>
            <p>Hoje (se disponivel) ou uma data futura.</p>
          </div>
          <div class="step">
            <div class="n">3</div>
            <h5>Pagar via PIX</h5>
            <p>Confirmacao rapida e liberacao automatica.</p>
          </div>
          <div class="step">
            <div class="n">4</div>
            <h5>Confirmacao</h5>
            <p>Responsavel e secretaria recebem o aviso.</p>
          </div>
        </div>
        <div class="hr"></div>
        <div class="grid-3">
          <div class="card info">
            <h4><?php echo htmlspecialchars($prices['planned']['label'], ENT_QUOTES, 'UTF-8'); ?></h4>
            <p><b><?php echo htmlspecialchars($prices['planned']['price'], ENT_QUOTES, 'UTF-8'); ?></b></p>
            <p><?php echo htmlspecialchars($prices['planned']['rule'], ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
          <div class="card info">
            <h4><?php echo htmlspecialchars($prices['urgent']['label'], ENT_QUOTES, 'UTF-8'); ?></h4>
            <p><b><?php echo htmlspecialchars($prices['urgent']['price'], ENT_QUOTES, 'UTF-8'); ?></b></p>
            <p><?php echo htmlspecialchars($prices['urgent']['rule'], ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
          <div class="card info">
            <h4>Regras</h4>
            <p>Apos 16h, compras para o dia atual nao ficam disponiveis.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="modalBg" id="rulesBg" role="dialog" aria-modal="true" aria-label="Regras do Day use Village">
    <div class="modal">
      <h4>Regras rapidas</h4>
      <ul>
        <li><b>Planejada:</b> para usar hoje, pague ate <b>10h</b> — <b>R$ 77,00</b>.</li>
        <li><b>Emergencial:</b> para usar hoje, apos <b>10h</b> — <b>R$ 97,00</b>.</li>
        <li><b>Apos 16h:</b> nao e possivel comprar para o dia atual.</li>
        <li>Pagamento disponivel via <b>PIX</b>.</li>
        <li>Depois do pagamento, responsavel e secretaria recebem confirmacao por e-mail.</li>
      </ul>
      <div class="closeRow">
        <button class="btn btn-ghost" type="button" onclick="closeRules()">Fechar</button>
      </div>
    </div>
  </div>

  <script>
    function openRules(){ document.getElementById('rulesBg').style.display='block'; }
    function closeRules(){ document.getElementById('rulesBg').style.display='none'; }
    document.getElementById('rulesBg').addEventListener('click', function(e){
      if(e.target.id === 'rulesBg') closeRules();
    });
  </script>
</body>
</html>
