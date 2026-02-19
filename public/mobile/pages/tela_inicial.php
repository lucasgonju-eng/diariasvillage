<style>
  .vi-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-height: 100vh;
    min-height: 100dvh;
    background: #fff;
    padding: 0 24px;
    padding-top: calc(48px + env(safe-area-inset-top, 0px));
    padding-bottom: calc(32px + env(safe-area-inset-bottom, 0px));
    overflow-x: hidden;
    font-family: 'Lexend', system-ui, sans-serif;
    -webkit-font-smoothing: antialiased;
  }

  /* ---- Top icon ---- */
  .vi-icon-circle {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: #e8eaf6;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
    flex-shrink: 0;
  }
  .vi-icon-circle .material-symbols-outlined {
    font-size: 36px;
    color: #18217b;
  }

  /* ---- Brand name ---- */
  .vi-brand {
    font-size: 18px;
    font-weight: 700;
    color: #18217b;
    margin-bottom: 28px;
    text-align: center;
  }

  /* ---- Hero illustration ---- */
  .vi-hero {
    width: 100%;
    max-width: 360px;
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 32px;
    flex-shrink: 0;
  }
  .vi-hero img {
    display: block;
    width: 100%;
    height: auto;
    object-fit: cover;
  }

  /* ---- Title ---- */
  .vi-title {
    font-size: 28px;
    font-weight: 800;
    color: #1a2035;
    text-align: center;
    line-height: 1.2;
    margin-bottom: 12px;
  }

  /* ---- Subtitle ---- */
  .vi-subtitle {
    font-size: 14px;
    font-weight: 400;
    color: #6b7280;
    text-align: center;
    line-height: 1.55;
    max-width: 320px;
    margin-bottom: 32px;
  }

  /* ---- Buttons container ---- */
  .vi-buttons {
    width: 100%;
    max-width: 360px;
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .vi-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 54px;
    border-radius: 27px;
    font-family: inherit;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.15s, transform 0.1s;
    -webkit-tap-highlight-color: transparent;
    border: none;
    outline: none;
  }
  .vi-btn:active {
    transform: scale(0.97);
  }

  .vi-btn--primary {
    background: #18217b;
    color: #fff;
    box-shadow: 0 4px 16px rgba(24, 33, 123, 0.25);
  }
  .vi-btn--primary:hover {
    opacity: 0.92;
  }

  .vi-btn--secondary {
    background: #fff;
    color: #18217b;
    border: 2px solid #d1d5db;
  }
  .vi-btn--secondary:hover {
    border-color: #18217b;
  }

  /* ---- Spacer ---- */
  .vi-spacer {
    flex: 1;
    min-height: 16px;
  }

  /* ---- Footer help ---- */
  .vi-help {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 28px;
    padding-bottom: 8px;
  }
  .vi-help span.material-symbols-outlined {
    font-size: 18px;
    color: #9ca3af;
  }
  .vi-help-text {
    font-size: 13px;
    font-weight: 500;
    color: #9ca3af;
  }
</style>

<div class="vi-wrap">
  <!-- Top icon -->
  <div class="vi-icon-circle">
    <span class="material-symbols-outlined">school</span>
  </div>

  <!-- Brand -->
  <div class="vi-brand">Diárias Village</div>

  <!-- Hero illustration -->
  <div class="vi-hero">
    <img src="/mobile/assets/img/tela_inicial_screen.png"
         alt="Crianças se divertindo no Village"
         width="360" height="280">
  </div>

  <!-- Title -->
  <h1 class="vi-title">Bem-vindo ao<br>Diárias Village</h1>

  <!-- Subtitle -->
  <p class="vi-subtitle">Acompanhe o dia a dia do seu filho com a segurança e a praticidade que você merece.</p>

  <!-- CTA Buttons -->
  <div class="vi-buttons">
    <button class="vi-btn vi-btn--primary" data-go="tela_cadastro">Primeiro Acesso</button>
    <button class="vi-btn vi-btn--secondary" data-go="tela_login">Já tenho cadastro</button>
  </div>

  <!-- Spacer -->
  <div class="vi-spacer"></div>

  <!-- Footer -->
  <div class="vi-help">
    <span class="material-symbols-outlined">help</span>
    <span class="vi-help-text">Precisa de ajuda?</span>
  </div>
</div>
