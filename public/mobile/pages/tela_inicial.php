<style>
  /* ── SaaS theme tokens ── */
  .vi {
    --bg: #F4F6FB;
    --ink: #0B1020;
    --muted: #556070;
    --line: #E6E9EF;
    --blue-900: #07162F;
    --blue-800: #081E3D;
    --blue-700: #0B2B63;
    --gold: #D6B25E;
    --gold-2: #E2C377;
    --radius: 22px;
    --radius-sm: 14px;
    --shadow: 0 18px 55px rgba(0,0,0,.28);
    --shadow-soft: 0 14px 40px rgba(11,16,32,.12);

    font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    color: var(--ink);
    background: var(--bg);
    min-height: 100vh;
    min-height: 100dvh;
    overflow-x: hidden;
    -webkit-font-smoothing: antialiased;
  }
  .vi *, .vi *::before, .vi *::after { box-sizing: border-box; }
  .vi a { color: inherit; text-decoration: none; }

  /* ── Popup "Como deseja continuar?" ── */
  .vi-popup {
    position: fixed; inset: 0;
    display: flex; align-items: center; justify-content: center;
    background: rgba(0,0,0,.5);
    padding: 20px; z-index: 50;
  }
  .vi-popup.hidden { display: none; }
  .vi-popup-card {
    background: #fff; color: #1a2133;
    border-radius: 20px; padding: 32px;
    max-width: 400px; width: 100%;
    text-align: center;
    box-shadow: 0 24px 60px rgba(0,0,0,.25);
    border: 1px solid var(--line);
  }
  .vi-popup-card h3 {
    margin: 0 0 8px; font-size: 22px; font-weight: 800;
  }
  .vi-popup-card .vi-popup-sub {
    margin: 0 0 24px; font-size: 14px; color: var(--muted);
  }
  .vi-popup-opts { display: flex; flex-direction: column; gap: 12px; }
  .vi-popup-opts button {
    display: block; width: 100%;
    padding: 16px 24px; border-radius: 14px;
    font-weight: 700; font-size: 16px;
    font-family: inherit; cursor: pointer; border: none;
    transition: transform .15s, box-shadow .15s;
    -webkit-tap-highlight-color: transparent;
  }
  .vi-popup-opts button:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.12); }
  .vi-popup-opts button:active { transform: scale(.97); }
  .vi-popup-opts .vi-btn-primeiro {
    background: linear-gradient(135deg, var(--gold), var(--gold-2));
    color: #0B1020;
  }
  .vi-popup-opts .vi-btn-ja {
    background: #E8F0FF; color: #0A1B4D; border: 2px solid #BFD0EE;
  }
  .vi-popup-fechar {
    margin-top: 16px; font-size: 13px; color: var(--muted);
    cursor: pointer; background: none; border: none; font-family: inherit;
  }

  /* ── Hero ── */
  .vi-hero {
    position: relative;
    padding: calc(24px + env(safe-area-inset-top, 0px)) 20px 80px;
    color: #fff;
    background:
      radial-gradient(600px 240px at 15% 10%, rgba(214,178,94,.18), transparent 60%),
      radial-gradient(700px 280px at 90% 15%, rgba(255,255,255,.10), transparent 55%),
      linear-gradient(180deg, var(--blue-700), var(--blue-900));
  }
  .vi-hero-inner { max-width: 480px; margin: 0 auto; }

  /* Brand bar */
  .vi-topbar {
    display: flex; align-items: center; justify-content: space-between;
    gap: 14px; margin-bottom: 22px;
  }
  .vi-brand { display: flex; align-items: center; gap: 10px; }
  .vi-brand-mark {
    width: 32px; height: 32px; border-radius: 10px;
    background: linear-gradient(135deg, var(--gold), var(--gold-2));
    box-shadow: 0 6px 16px rgba(214,178,94,.25);
    flex-shrink: 0;
  }
  .vi-brand-title { font-weight: 800; letter-spacing: .12em; font-size: 11px; }
  .vi-brand-sub { font-size: 11px; opacity: .7; margin-top: 1px; }

  /* Pill */
  .vi-pill {
    display: inline-flex; align-items: center;
    padding: 7px 14px; border-radius: 999px;
    border: 1px solid rgba(255,255,255,.18);
    background: rgba(255,255,255,.08);
    backdrop-filter: blur(10px);
    font-size: 12px; margin-bottom: 14px;
  }

  /* Title & lead */
  .vi-hero h1 {
    margin: 0 0 10px; font-size: 34px;
    line-height: 1.08; letter-spacing: -.02em; font-weight: 800;
  }
  .vi-lead {
    margin: 0 0 20px; font-size: 15px;
    opacity: .9; line-height: 1.5;
  }

  /* CTA row */
  .vi-cta { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
  .vi-btn {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 14px 18px; border-radius: 16px;
    font-weight: 800; font-size: 15px; font-family: inherit;
    border: 1px solid transparent; cursor: pointer;
    transition: transform .12s, filter .12s;
    -webkit-tap-highlight-color: transparent;
  }
  .vi-btn:active { transform: translateY(1px); }
  .vi-btn-gold {
    background: linear-gradient(180deg, var(--gold-2), var(--gold));
    color: #141414;
    box-shadow: 0 12px 30px rgba(214,178,94,.26);
    flex: 1; min-width: 0;
  }
  .vi-btn-ghost {
    background: rgba(255,255,255,.06);
    border-color: rgba(255,255,255,.22);
    color: #fff; flex: 1; min-width: 0;
  }
  .vi-btn-ghost:hover { background: rgba(255,255,255,.09); }

  /* Micro chips */
  .vi-chips { display: flex; gap: 8px; flex-wrap: wrap; }
  .vi-chip {
    font-size: 11px; padding: 6px 10px; border-radius: 999px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.14);
    opacity: .85;
  }

  /* Wave */
  .vi-wave {
    position: absolute; left: 0; right: 0; bottom: -1px;
    width: 100%; height: 72px; display: block;
    fill: var(--bg);
  }

  /* ── Steps card ── */
  .vi-card {
    background: rgba(255,255,255,.96); color: var(--ink);
    border-radius: var(--radius); padding: 18px;
    box-shadow: var(--shadow); border: 1px solid rgba(255,255,255,.55);
    margin-top: 22px;
  }
  .vi-card h3 { margin: 4px 0 4px; font-size: 17px; font-weight: 800; }
  .vi-card-muted { margin: 0 0 12px; color: var(--muted); font-size: 13px; }
  .vi-steps { display: grid; gap: 10px; }
  .vi-step {
    background: #fff; border: 1px solid var(--line);
    border-radius: var(--radius-sm); padding: 14px;
    display: flex; gap: 12px; align-items: center;
    box-shadow: 0 6px 16px rgba(11,16,32,.05);
  }
  .vi-step-n {
    width: 34px; height: 34px; border-radius: 10px;
    background: #EEF2FF; display: flex; align-items: center;
    justify-content: center; font-weight: 800; color: #23305a;
    flex: 0 0 auto; font-size: 14px;
  }
  .vi-step-t { font-weight: 800; font-size: 14px; }
  .vi-step-d { color: var(--muted); font-size: 12px; margin-top: 2px; }
  .vi-note {
    margin-top: 10px; padding: 10px 14px; border-radius: 14px;
    background: #F3F6FF; border: 1px solid #E2E8FF;
    color: #1f2a44; font-size: 12px; line-height: 1.5;
  }

  /* ── Pricing section ── */
  .vi-section {
    padding: 32px 20px;
    max-width: 480px; margin: 0 auto;
  }
  .vi-section-alt { background: linear-gradient(180deg, #F7F9FF, #F4F6FB); padding: 32px 20px; }
  .vi-section-alt .vi-section-inner { max-width: 480px; margin: 0 auto; }
  .vi-section h2 { margin: 0 0 4px; font-size: 26px; letter-spacing: -.02em; font-weight: 800; }
  .vi-section .vi-sub { margin: 0 0 16px; color: var(--muted); font-size: 14px; }

  .vi-pay {
    background: #fff; border-radius: var(--radius);
    border: 1px solid var(--line); box-shadow: var(--shadow-soft);
    padding: 18px;
  }
  .vi-pay-line { margin-bottom: 10px; }
  .vi-pay-line strong { display: block; font-size: 15px; }
  .vi-pay-line span { color: var(--muted); font-size: 13px; }
  .vi-pay .vi-btn { width: 100%; margin-top: 6px; }
  .vi-pay .vi-info {
    margin-top: 14px; padding: 12px 14px; border-radius: 14px;
    background: #F3F6FF; border: 1px solid #E2E8FF;
    color: #1f2a44; font-size: 13px; line-height: 1.5;
  }

  /* ── Rules ── */
  .vi-rules {
    background: #fff; border-radius: var(--radius);
    border: 1px solid var(--line); box-shadow: var(--shadow-soft);
    padding: 16px 18px;
  }
  .vi-rules ul { margin: 0; padding-left: 18px; }
  .vi-rules li { margin: 8px 0; font-size: 14px; color: #1a2133; line-height: 1.5; }

  /* ── Footer ── */
  .vi-footer {
    background: rgba(7,22,47,.92); color: rgba(255,255,255,.78);
    padding: 18px 20px; font-size: 12px; text-align: center;
    padding-bottom: calc(18px + env(safe-area-inset-bottom, 0px));
  }
</style>

<div class="vi">

  <!-- ═══ Popup "Como deseja continuar?" ═══ -->
  <div class="vi-popup" id="viPopup">
    <div class="vi-popup-card">
      <h3>Como deseja continuar?</h3>
      <p class="vi-popup-sub">Escolha uma opção para começar.</p>
      <div class="vi-popup-opts">
        <button class="vi-btn-primeiro" data-go="tela_cadastro">É seu primeiro acesso?</button>
        <button class="vi-btn-ja" data-go="tela_login">Já tem cadastro?</button>
      </div>
      <button type="button" class="vi-popup-fechar" id="viPopupFechar">Continuar navegando</button>
    </div>
  </div>

  <!-- ═══ Hero ═══ -->
  <section class="vi-hero">
    <div class="vi-hero-inner">

      <div class="vi-topbar">
        <div class="vi-brand">
          <div class="vi-brand-mark"></div>
          <div>
            <div class="vi-brand-title">DIÁRIAS VILLAGE</div>
            <div class="vi-brand-sub">Pagamento rápido do Day use Village</div>
          </div>
        </div>
      </div>

      <div class="vi-pill">Plataforma oficial do Day use Village</div>

      <h1>Resolve em<br>minutos.<br>Sem fila.</h1>

      <p class="vi-lead">Escolha o aluno, selecione a data, pague via PIX e pronto: a liberação acontece automaticamente.</p>

      <div class="vi-cta">
        <button class="vi-btn vi-btn-gold" data-go="tela_cadastro">Primeiro acesso</button>
        <button class="vi-btn vi-btn-ghost" data-go="tela_login">Entrar</button>
      </div>

      <div class="vi-chips">
        <span class="vi-chip">Confirmação por e-mail</span>
        <span class="vi-chip">Processo seguro</span>
        <span class="vi-chip">Secretaria avisada</span>
      </div>

      <!-- Steps card -->
      <div class="vi-card">
        <h3>Você só precisa fazer isso</h3>
        <p class="vi-card-muted"><?php
          date_default_timezone_set('America/Sao_Paulo');
          $h = (int) date('G');
          if ($h >= 16)      echo 'Após 16h, só é possível comprar para uma data futura.';
          elseif ($h >= 10)  echo 'Para hoje, agora a compra entra como Emergencial.';
          else               echo 'Para hoje, ainda dá para Planejada até 10h.';
        ?></p>
        <div class="vi-steps">
          <div class="vi-step">
            <div class="vi-step-n">1</div>
            <div>
              <div class="vi-step-t">Escolher o aluno</div>
              <div class="vi-step-d">Lista oficial importada pela escola.</div>
            </div>
          </div>
          <div class="vi-step">
            <div class="vi-step-n">2</div>
            <div>
              <div class="vi-step-t">Selecionar a data</div>
              <div class="vi-step-d">Hoje (se disponível) ou data futura.</div>
            </div>
          </div>
          <div class="vi-step">
            <div class="vi-step-n">3</div>
            <div>
              <div class="vi-step-t">Pagar via PIX</div>
              <div class="vi-step-d">Confirmação rápida e liberação automática.</div>
            </div>
          </div>
        </div>
        <div class="vi-note">Após 16h, compras para o dia atual não ficam disponíveis.</div>
      </div>
    </div>

    <svg class="vi-wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
      <path d="M0,64 C240,120 480,120 720,72 C960,24 1200,24 1440,72 L1440,120 L0,120 Z"></path>
    </svg>
  </section>

  <!-- ═══ Pagamento ═══ -->
  <div class="vi-section">
    <h2>Pagamento da diária</h2>
    <p class="vi-sub">Sem fricção. Sem conversa. Só resolver.</p>

    <div class="vi-pay">
      <div class="vi-pay-line">
        <strong>Planejada — R$ 77,00</strong>
        <span>Para usar hoje: pague até 10h</span>
      </div>
      <div class="vi-pay-line">
        <strong>Emergencial — R$ 97,00</strong>
        <span>Para usar hoje: após 10h</span>
      </div>
      <button class="vi-btn vi-btn-gold" data-go="tela_cadastro">Primeiro acesso</button>
      <div class="vi-info">
        <strong>Importante:</strong> não é necessário escolher tipo de diária.
        O sistema aplica automaticamente a regra correta conforme a hora do pagamento.
      </div>
    </div>
  </div>

  <!-- ═══ Regras ═══ -->
  <div class="vi-section-alt">
    <div class="vi-section-inner">
      <h2>Regras</h2>
      <p class="vi-sub">Horários, valores e cancelamento seguem as diretrizes do Einstein Village.</p>
      <div class="vi-rules">
        <ul>
          <li><b>Planejada:</b> para usar hoje, pague até <b>10h</b> — <b>R$ 77,00</b>.</li>
          <li><b>Emergencial:</b> para usar hoje, após <b>10h</b> — <b>R$ 97,00</b>.</li>
          <li><b>Após 16h:</b> não é possível comprar para o dia atual.</li>
          <li>Pagamento disponível via <b>PIX</b>.</li>
          <li>Depois do pagamento, responsável e secretaria recebem confirmação por e-mail.</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- ═══ Footer ═══ -->
  <div class="vi-footer">
    Diárias Village • Sistema de pagamento e controle de acesso
  </div>
</div>

<script>
(function () {
  var popup = document.getElementById('viPopup');
  var fechar = document.getElementById('viPopupFechar');
  if (!popup || !fechar) return;

  fechar.addEventListener('click', function () { popup.classList.add('hidden'); });
  popup.addEventListener('click', function (e) {
    if (e.target === popup) popup.classList.add('hidden');
  });
})();
</script>
