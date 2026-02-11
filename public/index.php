<?php
/**
 * Landing Page - Diárias Village (layout referência)
 * Ajuste somente o $continueUrl se necessario.
 */
$continueUrl = '/primeiro-acesso.php';
$brand = [
  'name' => 'Diárias Village',
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
$timeHint = 'Após 16h, só é possível comprar para uma data futura.';
} elseif ($hour >= 10) {
  $timeHint = 'Para hoje, agora a compra entra como Emergencial.';
} else {
  $timeHint = 'Para hoje, ainda da para Planejada ate 10h.';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="description" content="Pague o Day use Village em minutos. Sem fila, sem burocracia. PIX e liberação automática." />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/style.css?v=5">
  <style>
    .modalBg{
      position:fixed;
      inset:0;
      display:none;
      background: rgba(0,0,0,.55);
      padding: 16px;
      z-index: 40;
    }
    .modal{
      max-width: 720px;
      margin: 6vh auto 0;
      background: #fff;
      color: #1a2133;
      border:1px solid var(--line);
      border-radius: 18px;
      padding: 16px;
      box-shadow: var(--shadow-soft);
    }
    .modal h4{margin:6px 0 10px}
    .modal ul{margin:0;padding-left:18px;color:var(--muted);font-size:13px;line-height:1.55}
    .modal .closeRow{display:flex;justify-content:flex-end;margin-top:12px}
    /* Popup primeiro acesso */
    .popup-inicio{
      position:fixed;
      inset:0;
      display:flex;
      align-items:center;
      justify-content:center;
      background:rgba(0,0,0,.5);
      padding:20px;
      z-index:50;
    }
    .popup-inicio.hidden{display:none}
    .popup-inicio .popup-card{
      background:#fff;
      color:#1a2133;
      border-radius:20px;
      padding:32px;
      max-width:420px;
      width:100%;
      text-align:center;
      box-shadow:0 24px 60px rgba(0,0,0,.25);
      border:1px solid var(--line);
    }
    .popup-inicio .popup-card h3{
      margin:0 0 20px;
      font-size:22px;
      font-weight:800;
    }
    .popup-inicio .popup-options{
      display:flex;
      flex-direction:column;
      gap:12px;
      margin-top:24px;
    }
    .popup-inicio .popup-options a{
      display:block;
      padding:16px 24px;
      border-radius:14px;
      font-weight:700;
      font-size:16px;
      transition:transform .15s, box-shadow .15s;
    }
    .popup-inicio .popup-options a:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.12)}
    .popup-inicio .popup-options .btn-primeiro{
      background:linear-gradient(135deg, var(--gold), var(--gold-2));
      color:#0B1020;
      border:none;
    }
    .popup-inicio .popup-options .btn-ja-cadastro{
      background:#E8F0FF;
      color:#0A1B4D;
      border:2px solid #BFD0EE;
    }
    .popup-inicio .popup-fechar{
      margin-top:16px;
      font-size:13px;
      color:var(--muted);
      cursor:pointer;
      background:none;
      border:none;
    }
  </style>
</head>

<body>
  <div class="popup-inicio" id="popupInicio" role="dialog" aria-modal="true" aria-labelledby="popupInicioTitle">
    <div class="popup-card">
      <h3 id="popupInicioTitle">Como deseja continuar?</h3>
      <p class="muted">Escolha uma opção para começar.</p>
      <div class="popup-options">
        <a href="/primeiro-acesso.php" class="btn-primeiro">É seu primeiro acesso?</a>
        <a href="/login.php" class="btn-ja-cadastro">Já tem cadastro?</a>
      </div>
      <button type="button" class="popup-fechar" onclick="fecharPopupInicio()">Continuar navegando</button>
    </div>
  </div>

  <header class="hero" id="top">
    <div class="container">

      <div class="topbar">
        <div class="brand">
          <span class="brand-mark" aria-hidden="true"></span>
          <div class="brand-text">
            <div class="brand-title"><?php echo strtoupper(htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8')); ?></div>
            <div class="brand-sub"><?php echo htmlspecialchars($brand['tag'], ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
        </div>

        <button class="btn btn-ghost btn-sm" type="button" onclick="openRules()" aria-label="Ver regras">Regras</button>
      </div>

      <div class="hero-grid">
        <div class="hero-left">
          <div class="pill">Plataforma oficial do Day use Village</div>

          <h1>Resolve em minutos.<br>Sem fila. Sem estresse.</h1>
          <p class="lead">
            Escolha o aluno, selecione a data, pague via PIX e pronto:
            a liberação acontece automaticamente.
          </p>

          <div class="cta">
            <a class="btn btn-primary" href="<?php echo htmlspecialchars($continueUrl, ENT_QUOTES, 'UTF-8'); ?>">Primeiro acesso</a>
            <a class="btn btn-primary" href="/login.php">Entrar</a>
            <button class="btn btn-ghost" type="button" onclick="openRules()">Ver regras</button>
          </div>

          <div class="microchips" role="list">
            <span class="microchip" role="listitem">Confirmação por e-mail</span>
            <span class="microchip" role="listitem">Processo seguro</span>
            <span class="microchip" role="listitem">Secretaria avisada automaticamente</span>
          </div>
        </div>

        <aside class="hero-card" aria-label="Fluxo rapido">
          <h3>Você só precisa fazer isso</h3>
          <p class="muted"><?php echo htmlspecialchars($timeHint, ENT_QUOTES, 'UTF-8'); ?></p>

          <div class="steps">
            <div class="step">
              <div class="step-n">1</div>
              <div>
                <div class="step-t">Escolher o aluno</div>
                <div class="muted">Lista oficial importada pela escola.</div>
              </div>
            </div>
            <div class="step">
              <div class="step-n">2</div>
              <div>
                <div class="step-t">Selecionar a data</div>
                <div class="muted">Hoje (se disponível) ou uma data futura.</div>
              </div>
            </div>
            <div class="step">
              <div class="step-n">3</div>
              <div>
                <div class="step-t">Pagar via PIX</div>
                <div class="muted">Confirmação rápida e liberação automática.</div>
              </div>
            </div>
          </div>

          <div class="note">
            Após 16h, compras para o dia atual não ficam disponíveis.
          </div>
        </aside>
      </div>
    </div>

    <!-- wave -->
    <svg class="wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
      <path d="M0,64 C240,120 480,120 720,72 C960,24 1200,24 1440,72 L1440,120 L0,120 Z"></path>
    </svg>
  </header>

  <main>
    <section class="section" id="pagar">
      <div class="container">
        <div class="section-head">
          <h2>Pagamento da diária</h2>
          <p class="muted">Sem fricção. Sem conversa. Só resolver.</p>
        </div>

        <div class="pay-card">
          <div class="pay-lines">
            <div class="line">
              <strong><?php echo htmlspecialchars($prices['planned']['label'], ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars($prices['planned']['price'], ENT_QUOTES, 'UTF-8'); ?></strong>
              <span class="muted"><?php echo htmlspecialchars($prices['planned']['rule'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="line">
              <strong><?php echo htmlspecialchars($prices['urgent']['label'], ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars($prices['urgent']['price'], ENT_QUOTES, 'UTF-8'); ?></strong>
              <span class="muted"><?php echo htmlspecialchars($prices['urgent']['rule'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </div>

          <a class="btn btn-primary btn-block" href="<?php echo htmlspecialchars($continueUrl, ENT_QUOTES, 'UTF-8'); ?>">Primeiro acesso</a>

          <div class="info">
            <strong>Importante:</strong> não é necessário escolher tipo de diária. O sistema aplica automaticamente a regra correta conforme a hora do pagamento.
            <?php if ($timeHint !== ''): ?>
              <br><?php echo htmlspecialchars($timeHint, ENT_QUOTES, 'UTF-8'); ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <section class="section section-alt" id="como-funciona">
      <div class="container">
        <div class="section-head">
          <h2>Como funciona</h2>
          <p class="muted">Fluxo curto, sem cadastro longo.</p>
        </div>

        <div class="steps">
          <div class="step">
            <div class="step-n">1</div>
            <div>
              <div class="step-t">Inicie o pagamento</div>
              <div class="muted">Acesse e siga o fluxo.</div>
            </div>
          </div>

          <div class="step">
            <div class="step-n">2</div>
            <div>
              <div class="step-t">Informe o essencial</div>
              <div class="muted">Dados mínimos para identificação e pagamento.</div>
            </div>
          </div>

          <div class="step">
            <div class="step-n">3</div>
            <div>
              <div class="step-t">Pague via PIX</div>
              <div class="muted">Confirmou, regularizou.</div>
            </div>
          </div>
        </div>

        <div class="final-cta">
          <div>
            <div class="final-title">Resolver agora</div>
            <div class="muted">Pagamento rápido. Acesso regularizado.</div>
          </div>
          <a class="btn btn-primary" href="<?php echo htmlspecialchars($continueUrl, ENT_QUOTES, 'UTF-8'); ?>">Primeiro acesso</a>
        </div>
      </div>
    </section>

    <section class="section" id="regras">
      <div class="container">
        <div class="section-head">
          <h2>Regras</h2>
          <p class="muted">Horários, valores e cancelamento seguem as diretrizes do Einstein Village.</p>
        </div>

        <div class="rules-card">
          <ul>
            <li><b>Planejada:</b> para usar hoje, pague ate <b>10h</b> — <b>R$ 77,00</b>.</li>
            <li><b>Emergencial:</b> para usar hoje, após <b>10h</b> — <b>R$ 97,00</b>.</li>
            <li><b>Após 16h:</b> não é possível comprar para o dia atual.</li>
            <li>Pagamento disponível via <b>PIX</b>.</li>
            <li>Depois do pagamento, responsável e secretaria recebem confirmação por e-mail.</li>
          </ul>
        </div>
      </div>
    </section>
  </main>

  <footer class="footer">
    <div class="container">
      Diárias Village • Sistema de pagamento e controle de acesso
      <a class="tinyLink" href="/admin/dashboard.php" aria-label="Acesso administrativo">Admin</a>
    </div>
  </footer>

  <div class="modalBg" id="rulesBg" role="dialog" aria-modal="true" aria-label="Regras do Day use Village">
    <div class="modal">
      <h4>Regras rápidas</h4>
      <ul>
        <li><b>Planejada:</b> para usar hoje, pague ate <b>10h</b> — <b>R$ 77,00</b>.</li>
        <li><b>Emergencial:</b> para usar hoje, após <b>10h</b> — <b>R$ 97,00</b>.</li>
        <li><b>Após 16h:</b> não é possível comprar para o dia atual.</li>
        <li>Pagamento disponível via <b>PIX</b>.</li>
        <li>Depois do pagamento, responsável e secretaria recebem confirmação por e-mail.</li>
      </ul>
      <div class="closeRow">
        <button class="btn btn-ghost btn-sm" type="button" onclick="closeRules()">Fechar</button>
      </div>
    </div>
  </div>

  <script>
    function fecharPopupInicio(){
      document.getElementById('popupInicio').classList.add('hidden');
    }
    document.getElementById('popupInicio').addEventListener('click', function(e){
      if(e.target.id === 'popupInicio') fecharPopupInicio();
    });
    function openRules(){ document.getElementById('rulesBg').style.display='block'; }
    function closeRules(){ document.getElementById('rulesBg').style.display='none'; }
    document.getElementById('rulesBg').addEventListener('click', function(e){
      if(e.target.id === 'rulesBg') closeRules();
    });
  </script>
</body>
</html>
