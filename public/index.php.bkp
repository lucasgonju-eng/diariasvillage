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
      --bg:#0B0B0F;
      --bg2:#111827;
      --card:#FFF7ED;
      --card2:#FFFFFF;
      --soft:#0F172A;
      --text:#0F172A;
      --textOnDark:#F8FAFC;
      --muted:#475569;
      --mutedOnDark: rgba(248,250,252,.75);
      --line: rgba(255,255,255,.10);
      --lineCard: rgba(2,6,23,.10);
      --accent:#F97316;
      --accent2:#EA580C;
      --good:#16A34A;
      --danger:#DC2626;
      --shadow: 0 18px 55px rgba(0,0,0,.35);
      --shadowSoft: 0 14px 40px rgba(2,6,23,.10);
      --radius: 18px;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Apple Color Emoji", "Segoe UI Emoji";
      color:var(--textOnDark);
      background:
        radial-gradient(1100px 700px at 15% 0%, rgba(249,115,22,.22), transparent 55%),
        radial-gradient(900px 600px at 90% 20%, rgba(234,88,12,.18), transparent 55%),
        radial-gradient(1000px 700px at 50% 100%, rgba(255,247,237,.06), transparent 60%),
        linear-gradient(180deg, #0B0B0F 0%, #0F172A 100%);
    }
    a{color:inherit; text-decoration:none}
    .wrap{max-width:1100px; margin:0 auto; padding:22px 18px 50px}
    .top{
      display:flex; align-items:center; justify-content:space-between;
      gap:14px; padding:10px 2px 18px;
    }
    .brand{
      display:flex; align-items:center; gap:10px;
    }
    .logo{
      width:38px; height:38px; border-radius:12px;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      box-shadow: 0 10px 30px rgba(249,115,22,.25);
    }
    .brand h1{font-size:14px; margin:0; letter-spacing:.2px}
    .brand p{font-size:12px; margin:2px 0 0; color:var(--mutedOnDark)}
    .pill{
      display:inline-flex; align-items:center; gap:8px;
      padding:10px 12px; border:1px solid var(--line);
      background: rgba(255,255,255,.04);
      border-radius:999px; color:var(--mutedOnDark);
      font-size:12px;
    }

    .grid{
      display:grid; gap:18px;
      grid-template-columns: 1.1fr .9fr;
      margin-top:10px;
    }
    @media (max-width: 980px){
      .grid{grid-template-columns:1fr}
    }

    .hero{
      border:1px solid var(--lineCard);
      background: var(--card);
      color: var(--text);
      border-radius: var(--radius);
      box-shadow: var(--shadowSoft);
      padding:24px;
      position:relative;
      overflow:hidden;
      min-height: 320px;
      animation: fadeUp .55s ease both;
    }
    .hero:before{
      content:"";
      position:absolute; inset:-40px -40px auto auto;
      width:220px; height:220px; border-radius:50%;
      background: radial-gradient(circle at 30% 30%, rgba(249,115,22,.35), transparent 65%);
      filter: blur(2px);
      animation: floatGlow 6s ease-in-out infinite;
    }
    .kicker{
      display:inline-flex; gap:8px; align-items:center;
      padding:8px 10px; border-radius:999px;
      border:1px solid var(--lineCard);
      background: var(--card2);
      color:var(--muted);
      font-size:12px;
    }
    .kicker b{color:var(--text); font-weight:600}
    .hero h2{
      margin:14px 0 10px;
      font-size:34px; line-height:1.08;
      letter-spacing:-.6px;
    }
    .hero h2 span{color: var(--text)}
    .hero .sub{
      margin:0 0 18px; color:var(--muted); font-size:15px; line-height:1.5;
      max-width: 54ch;
    }
    .ctaRow{
      display:flex; gap:12px; align-items:center; flex-wrap:wrap;
      margin-top: 6px;
    }
    .btn{
      display:inline-flex; align-items:center; justify-content:center;
      padding:14px 16px;
      border-radius: 14px;
      border:1px solid rgba(15,23,42,.12);
      font-weight:700;
      letter-spacing:.2px;
      cursor:pointer;
      transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
      user-select:none;
      position: relative;
      overflow: hidden;
    }
    .btn:active{transform: translateY(1px)}
    .btnPrimary{
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      color: #FFFFFF;
      box-shadow: 0 16px 40px rgba(249,115,22,.25);
    }
    .btnPrimary:before{
      content:"";
      position:absolute;
      top:0; left:-60%;
      width:40%; height:100%;
      background: linear-gradient(120deg, transparent, rgba(255,255,255,.45), transparent);
      transform: skewX(-20deg);
      opacity: 0;
    }
    .btnPrimary:hover:before{
      animation: sheen .9s ease;
      opacity: 1;
    }
    .btnGhost{
      background: var(--card2);
      color: var(--text);
    }
    .trust{
      display:flex; gap:10px; flex-wrap:wrap; margin-top:14px;
      color: var(--muted); font-size:12px;
    }
    .trust .chip{
      padding:8px 10px; border-radius:999px;
      border:1px solid var(--lineCard);
      background: var(--card2);
      display:inline-flex; gap:8px; align-items:center;
      transition: transform .18s ease, box-shadow .18s ease;
    }
    .trust .chip:hover{transform: translateY(-1px)}

    .side{
      border:1px solid var(--lineCard);
      background: var(--card);
      color: var(--text);
      border-radius: var(--radius);
      padding:18px;
      box-shadow: var(--shadowSoft);
      animation: fadeUp .65s ease both;
      animation-delay: .05s;
    }

    .steps{
      display:grid; gap:10px; margin:6px 0 4px;
    }
    .step{
      display:flex; gap:12px; align-items:flex-start;
      padding:12px; border-radius: 14px;
      border:1px solid var(--lineCard);
      background: var(--card2);
      transition: transform .18s ease, box-shadow .18s ease;
    }
    .step:hover{transform: translateY(-1px)}
    .num{
      width:30px; height:30px; border-radius:10px;
      display:flex; align-items:center; justify-content:center;
      font-weight:800;
      background: rgba(249,115,22,.18);
      border: 1px solid rgba(249,115,22,.35);
      color: var(--text);
      flex: 0 0 auto;
    }
    .step b{display:block; margin:0 0 2px; font-size:13px}
    .step p{margin:0; color:var(--muted); font-size:12px; line-height:1.45}

    .sectionTitle{
      display:flex; align-items:center; justify-content:space-between;
      gap:10px; margin: 2px 0 10px;
    }
    .sectionTitle h3{margin:0; font-size:13px; letter-spacing:.2px}
    .hint{font-size:12px; color:var(--muted)}

    .pricing{
      display:grid; grid-template-columns:1fr 1fr; gap:10px;
      margin-top: 14px;
    }
    @media (max-width: 520px){
      .pricing{grid-template-columns:1fr}
    }
    .priceCard{
      border-radius: 16px;
      border:1px solid var(--lineCard);
      background: var(--card2);
      padding:14px;
      transition: transform .18s ease, box-shadow .18s ease;
    }
    .priceCard:hover{transform: translateY(-2px)}
    .priceCard .label{
      display:flex; align-items:center; justify-content:space-between;
      gap:10px; font-size:12px; color:var(--muted);
    }
    .badge{
      padding:6px 10px; border-radius:999px;
      border:1px solid var(--lineCard);
      background: var(--card);
      font-size:11px;
      color: var(--text);
    }
    .money{
      font-size:22px; font-weight:900; margin:8px 0 6px;
      letter-spacing:-.3px;
    }
    .rule{margin:0; color:var(--muted); font-size:12px; line-height:1.45}
    .good{border-color: rgba(22,163,74,.30)}
    .warn{border-color: rgba(249,115,22,.30)}
    .good .badge{border-color: rgba(22,163,74,.35)}
    .warn .badge{border-color: rgba(249,115,22,.35)}

    .footer{
      margin-top:18px;
      display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;
      color: var(--muted); font-size:12px;
      border-top:1px solid var(--lineCard);
      padding-top: 14px;
    }
    .tinyLink{opacity:.9; text-decoration:underline; text-decoration-color: rgba(15,23,42,.22)}
    .modalBg{
      position:fixed; inset:0; display:none;
      background: rgba(0,0,0,.55);
      padding: 16px;
    }
    .modal{
      max-width: 720px; margin: 6vh auto 0;
      background: var(--card2);
      color: var(--text);
      border:1px solid var(--lineCard);
      border-radius: 18px;
      padding: 16px;
      box-shadow: var(--shadowSoft);
      animation: fadeUp .4s ease both;
    }
    .banner{
      display:flex; align-items:center; gap:10px;
      padding:10px 12px; border-radius:12px;
      background: rgba(249,115,22,.12);
      border:1px solid rgba(249,115,22,.35);
      color: var(--text);
      font-size:12px;
      margin-bottom: 12px;
    }
    .banner span{font-size:14px}
    @keyframes fadeUp{
      from{opacity:0; transform: translateY(12px)}
      to{opacity:1; transform: translateY(0)}
    }
    @keyframes floatGlow{
      0%, 100%{transform: translateY(0)}
      50%{transform: translateY(6px)}
    }
    @keyframes sheen{
      0%{transform: translateX(-120%) skewX(-20deg)}
      100%{transform: translateX(240%) skewX(-20deg)}
    }

    @media (prefers-reduced-motion: reduce){
      *{animation:none !important; transition:none !important}
    }
    .modal h4{margin:6px 0 10px}
    .modal ul{margin:0; padding-left: 18px; color: var(--muted); font-size:13px; line-height:1.55}
    .modal .closeRow{display:flex; justify-content:flex-end; margin-top: 12px}
  </style>
</head>

<body>
  <div class="wrap">
    <header class="top">
      <div class="brand">
        <div class="logo" aria-hidden="true"></div>
        <div>
          <h1><?php echo htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
          <p><?php echo htmlspecialchars($brand['tag'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
      </div>

      <div class="pill" title="Status do pagamento">
        <span aria-hidden="true">⚡</span>
        <span>PIX com confirmacao rapida</span>
      </div>
    </header>

    <main class="grid">
      <section class="hero">
        <div class="banner"><span aria-hidden="true">🚧</span>Estamos aprimorando a plataforma. Obrigado por apoiar — tudo aqui e feito para sua comodidade.</div>
        <div class="kicker"><span aria-hidden="true">🏫</span> Plataforma oficial do <b>Day use Village</b></div>

        <h2>Resolve em minutos.<br><span>Sem fila. Sem estresse.</span></h2>
        <p class="sub">
          Escolha o aluno, selecione a data, pague via PIX e pronto:
          a liberacao acontece automaticamente.
        </p>

        <div class="ctaRow">
          <a class="btn btnPrimary" href="<?php echo htmlspecialchars($continueUrl, ENT_QUOTES, 'UTF-8'); ?>">Continuar</a>
          <button class="btn btnGhost" type="button" onclick="openRules()">Ver regras</button>
        </div>

        <div class="trust" aria-label="Garantias e confirmacao">
          <span class="chip">✅ Confirmacao por e-mail</span>
          <span class="chip">🔐 Processo seguro</span>
          <span class="chip">📩 Secretaria avisada automaticamente</span>
        </div>
      </section>

      <aside class="side">
        <div class="sectionTitle">
          <h3>Voce so precisa fazer isso</h3>
          <span class="hint"><?php echo htmlspecialchars($timeHint, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>

        <div class="steps">
          <div class="step">
            <div class="num">1</div>
            <div>
              <b>Escolher o aluno</b>
              <p>Lista oficial importada pela escola.</p>
            </div>
          </div>
          <div class="step">
            <div class="num">2</div>
            <div>
              <b>Selecionar a data</b>
              <p>Hoje (se disponivel) ou uma data futura.</p>
            </div>
          </div>
          <div class="step">
            <div class="num">3</div>
            <div>
              <b>Pagar via PIX</b>
              <p>Confirmacao rapida e liberacao automatica.</p>
            </div>
          </div>
        </div>

        <div class="pricing" aria-label="Precos">
          <div class="priceCard good">
            <div class="label">
              <span><?php echo htmlspecialchars($prices['planned']['label'], ENT_QUOTES, 'UTF-8'); ?></span>
              <span class="badge">para hoje</span>
            </div>
            <div class="money"><?php echo htmlspecialchars($prices['planned']['price'], ENT_QUOTES, 'UTF-8'); ?></div>
            <p class="rule"><?php echo htmlspecialchars($prices['planned']['rule'], ENT_QUOTES, 'UTF-8'); ?></p>
          </div>

          <div class="priceCard warn">
            <div class="label">
              <span><?php echo htmlspecialchars($prices['urgent']['label'], ENT_QUOTES, 'UTF-8'); ?></span>
              <span class="badge">para hoje</span>
            </div>
            <div class="money"><?php echo htmlspecialchars($prices['urgent']['price'], ENT_QUOTES, 'UTF-8'); ?></div>
            <p class="rule"><?php echo htmlspecialchars($prices['urgent']['rule'], ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
        </div>

        <div class="footer">
          <div>
            <div><b>Apos 16h</b>, compras para o dia atual nao ficam disponiveis.</div>
            <div>Voce consegue escolher uma data futura (Planejada).</div>
          </div>
          <div>
            <a class="tinyLink" href="<?php echo htmlspecialchars($continueUrl, ENT_QUOTES, 'UTF-8'); ?>">Ir para Continuar</a>
          </div>
        </div>
      </aside>
    </main>
  </div>

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
        <button class="btn btnGhost" type="button" onclick="closeRules()">Fechar</button>
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
