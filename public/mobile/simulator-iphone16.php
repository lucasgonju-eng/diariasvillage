<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Simulador iPhone 16 – Diárias Village</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      min-height: 100vh;
      background: linear-gradient(180deg, #1a1a2e 0%, #16213e 50%, #0f0f1a 100%);
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .device {
      width: 393px;
      height: 852px;
      background: #000;
      border-radius: 55px;
      padding: 12px;
      box-shadow:
        0 0 0 3px #1c1c1e,
        0 0 0 6px #2c2c2e,
        0 25px 80px rgba(0,0,0,.6),
        inset 0 0 6px rgba(255,255,255,.03);
      position: relative;
    }
    .screen {
      width: 100%;
      height: 100%;
      background: #000;
      border-radius: 45px;
      overflow: hidden;
      position: relative;
    }
    .dynamic-island {
      position: absolute;
      top: 12px;
      left: 50%;
      transform: translateX(-50%);
      width: 126px;
      height: 37px;
      background: #000;
      border-radius: 20px;
      z-index: 10;
      box-shadow: inset 0 0 0 2px rgba(255,255,255,.08);
    }
    .status-bar {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 54px;
      padding: 12px 22px 8px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 15px;
      font-weight: 600;
      color: #fff;
      z-index: 5;
      pointer-events: none;
      background: linear-gradient(180deg, rgba(0,0,0,.4) 0%, transparent 100%);
    }
    .status-bar span:first-child { margin-right: auto; }
    .status-bar .center { position: absolute; left: 50%; transform: translateX(-50%); }
    .status-bar .battery { margin-left: auto; }
    .app-iframe {
      position: absolute;
      top: 0;
      left: 0;
      width: 393px;
      height: 852px;
      border: none;
      border-radius: 45px;
    }
    .url-bar {
      margin-top: 20px;
      text-align: center;
      color: rgba(255,255,255,.7);
      font-size: 12px;
      max-width: 393px;
    }
    .url-bar a { color: #64b5f6; }
  </style>
</head>
<body>
  <div>
    <div class="device">
      <div class="screen">
        <div class="dynamic-island"></div>
        <div class="status-bar">
          <span>9:41</span>
          <span class="center"></span>
          <span class="battery">🔋 100%</span>
        </div>
        <iframe
          class="app-iframe"
          id="app-frame"
          src=""
          title="Diárias Village"
        ></iframe>
      </div>
    </div>
    <p class="url-bar">iPhone 16 (393×852) · <a href="#" id="open-link" target="_blank">Abrir app em nova aba</a></p>
  </div>
  <script>
    (function() {
      var base = (window.location.protocol === 'file:' || window.location.hostname !== 'village.einsteinhub.co')
        ? 'https://village.einsteinhub.co/mobile/'
        : (window.location.origin + '/mobile/');
      var appUrl = base + (base.indexOf('?') !== -1 ? '&' : '?') + 'page=tela_inicial';
      document.getElementById('app-frame').src = appUrl;
      document.getElementById('open-link').href = base;
    })();
  </script>
</body>
</html>
