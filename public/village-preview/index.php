<?php
/**
 * Landing Page - Diarias Village (layout preview)
 * Ajuste somente o $continueUrl se necessario.
 */
$continueUrl = '/village-preview/primeiro-acesso.php';
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
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="description" content="Pague o Day use Village em minutos. Sem fila, sem burocracia. PIX e liberacao automatica." />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css?v=4?v=finalv3">
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
  </style>
</head>

<body>
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
            a liberacao acontece automaticamente.
          </p>

          <div class="cta">
            <a class="btn btn-primary" href="<?php echo htmlspecialchars($continueUrl, ENT_QUOTES, 'UTF-8'); ?>">Continuar</a>
            <a class="btn btn-primary" href="/village-preview/login.php">Entrar</a>
            <button class="btn btn-ghost" type="button" onclick="openRules()">Ver regras</button>
          </div>

          <div class="microchips" role="list">
            <span class="microchip" role="listitem">Confirmacao por e-mail</span>
            <span class="microchip" role="listitem">Processo seguro</span>
            <span class="microchip" role="listitem">Secretaria avisada automaticamente</span>
          </div>
        </div>

        <aside class="hero-card" aria-label="Fluxo rapido">
          <h3>Voce so precisa fazer isso</h3>
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
                <div class="muted">Hoje (se disponivel) ou uma data futura.</div>
              </div>
            </div>
            <div class="step">
              <div class="step-n">3</div>
              <div>
                <div class="step-t">Pagar via PIX</div>
                <div class="muted">Confirmacao rapida e liberacao automatica.</div>
              </div>
            </div>
          </div>

          <div class="note">
            Apos 16h, compras para o dia atual nao ficam disponiveis.
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
          <h2>Pagamento da diaria</h2>
          <p class="muted">Sem friccao. Sem conversa. So resolver.</p>
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

          <a class="btn btn-primary btn-block" href="<?php echo htmlspecialchars($continueUrl, ENT_QUOTES, 'UTF-8'); ?>">Continuar para pagamento</a>

          <div class="info">
            <strong>Importante:</strong> nao e necessario escolher tipo de diaria. O sistema aplica automaticamente a regra correta conforme a hora do pagamento.
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
              <div class="muted">Dados minimos para identificacao e pagamento.</div>
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
            <div class="muted">Pagamento rapido. Acesso regularizado.</div>
          </div>
          <a class="btn btn-primary" href="<?php echo htmlspecialchars($continueUrl, ENT_QUOTES, 'UTF-8'); ?>">Continuar</a>
        </div>
      </div>
    </section>

    <section class="section" id="regras">
      <div class="container">
        <div class="section-head">
          <h2>Regras</h2>
          <p class="muted">Horarios, valores e cancelamento seguem as diretrizes do Einstein Village.</p>
        </div>

        <div class="rules-card">
          <ul>
            <li><b>Planejada:</b> para usar hoje, pague ate <b>10h</b> — <b>R$ 77,00</b>.</li>
            <li><b>Emergencial:</b> para usar hoje, apos <b>10h</b> — <b>R$ 97,00</b>.</li>
            <li><b>Apos 16h:</b> nao e possivel comprar para o dia atual.</li>
            <li>Pagamento disponivel via <b>PIX</b>.</li>
            <li>Depois do pagamento, responsavel e secretaria recebem confirmacao por e-mail.</li>
          </ul>
        </div>
      </div>
    </section>
  </main>

  <footer class="footer">
    <div class="container">
      Diarias Village • Sistema de pagamento e controle de acesso
    </div>
  </footer>

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
        <button class="btn btn-ghost btn-sm" type="button" onclick="closeRules()">Fechar</button>
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
