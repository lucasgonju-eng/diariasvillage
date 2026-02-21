<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = !empty($_SESSION['user']);

// ── New router: ?r= (friendly app routes) ──
$appRoutes = [
    'login'           => 'login',
    'primeiro-acesso' => 'primeiro-acesso',
    'grade'           => 'grade',
    'grade-oficina'   => 'grade-oficina',
    'perfil'          => 'perfil',
    'resumo'          => 'resumo',
];

$r = $_GET['r'] ?? null;

// Auto-redirect: /mobile/ with no params → login or grade
if ($r === null && !isset($_GET['page'])) {
    $dest = $isLoggedIn ? 'grade' : 'login';
    header('Location: /mobile/?r=' . $dest);
    exit;
}

// If ?r= is set, serve from /app/
if ($r !== null) {
    $safeRoute = $appRoutes[$r] ?? null;
    if ($safeRoute === null) {
        header('Location: /mobile/?r=login');
        exit;
    }
    $authRequired = in_array($safeRoute, ['grade', 'grade-oficina', 'perfil', 'resumo'], true);
    if ($authRequired && !$isLoggedIn) {
        header('Location: /mobile/?r=login');
        exit;
    }
    $appFile = __DIR__ . '/app/' . $safeRoute . '.php';
    if (!file_exists($appFile)) {
        header('Location: /mobile/?r=login');
        exit;
    }

    $appTitles = [
        'login'           => 'Login – Diárias Village',
        'primeiro-acesso' => 'Primeiro Acesso – Diárias Village',
        'grade'           => 'Workshops – Diárias Village',
        'grade-oficina'   => 'Grade de Oficinas – Diárias Village',
        'perfil'          => 'Perfil – Diárias Village',
        'resumo'          => 'Resumo – Diárias Village',
    ];
    $title = $appTitles[$safeRoute] ?? 'Diárias Village';
    $currentRoute = $safeRoute;
    $pageFile = $appFile;

} else {
    // ── Legacy router: ?page= (backward-compat) ──
    $allowed = [
        'tela_inicial',
        'tela_login',
        'tela_cadastro',
        'grade_oficinas',
        'resumo_pedido',
        'simulator-iphone16',
    ];

    $page = $_GET['page'] ?? 'tela_inicial';
    if (!in_array($page, $allowed, true)) {
        $page = 'tela_inicial';
    }

    // Simulador iPhone 16 – servido inline (não depende de arquivo extra no deploy)
    if ($page === 'simulator-iphone16') {
        header('Content-Type: text/html; charset=utf-8');
        $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'village.einsteinhub.co') . '/mobile/';
        $appUrl = $base . '?page=tela_inicial';
        ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Simulador iPhone 16 – Diárias Village</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{min-height:100vh;background:linear-gradient(180deg,#1a1a2e 0%,#16213e 50%,#0f0f1a 100%);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;display:flex;align-items:center;justify-content:center;padding:24px}
    .device{width:393px;height:852px;background:#000;border-radius:55px;padding:12px;box-shadow:0 0 0 3px #1c1c1e,0 0 0 6px #2c2c2e,0 25px 80px rgba(0,0,0,.6),inset 0 0 6px rgba(255,255,255,.03);position:relative}
    .screen{width:100%;height:100%;background:#000;border-radius:45px;overflow:hidden;position:relative}
    .dynamic-island{position:absolute;top:12px;left:50%;transform:translateX(-50%);width:126px;height:37px;background:#000;border-radius:20px;z-index:10;box-shadow:inset 0 0 0 2px rgba(255,255,255,.08)}
    .status-bar{position:absolute;top:0;left:0;right:0;height:54px;padding:12px 22px 8px;display:flex;align-items:center;justify-content:space-between;font-size:15px;font-weight:600;color:#fff;z-index:5;pointer-events:none;background:linear-gradient(180deg,rgba(0,0,0,.4) 0%,transparent 100%)}
    .status-bar span:first-child{margin-right:auto}
    .status-bar .center{position:absolute;left:50%;transform:translateX(-50%)}
    .status-bar .battery{margin-left:auto}
    .app-iframe{position:absolute;top:0;left:0;width:393px;height:852px;border:none;border-radius:45px}
    .url-bar{margin-top:20px;text-align:center;color:rgba(255,255,255,.7);font-size:12px;max-width:393px}
    .url-bar a{color:#64b5f6}
  </style>
</head>
<body>
  <div>
    <div class="device">
      <div class="screen">
        <div class="dynamic-island"></div>
        <div class="status-bar"><span>9:41</span><span class="center"></span><span class="battery">🔋 100%</span></div>
        <iframe class="app-iframe" id="app-frame" src="<?= htmlspecialchars($appUrl) ?>" title="Diárias Village"></iframe>
      </div>
    </div>
    <p class="url-bar">iPhone 16 (393×852) · <a href="<?= htmlspecialchars($base) ?>" target="_blank">Abrir app em nova aba</a></p>
  </div>
</body>
</html>
        <?php
        exit;
    }

    $pageFile = __DIR__ . '/pages/' . $page . '.php';
    if (!file_exists($pageFile)) {
        $page     = 'tela_inicial';
        $pageFile = __DIR__ . '/pages/tela_inicial.php';
    }

    $titles = [
        'tela_inicial'   => 'Diárias Village',
        'tela_login'     => 'Login – Diárias Village',
        'tela_cadastro'  => 'Cadastro – Diárias Village',
        'grade_oficinas' => 'Workshops – Diárias Village',
        'resumo_pedido'  => 'Resumo do Pedido – Diárias Village',
    ];
    $title = $titles[$page] ?? 'Diárias Village';
    $currentRoute = null;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <title><?= htmlspecialchars($title) ?></title>

  <!-- PWA -->
  <meta name="theme-color" content="#0F2A75">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Village">
  <link rel="manifest" href="/mobile/manifest.webmanifest">
  <link rel="apple-touch-icon" href="/mobile/assets/img/icon-192.png">

  <!-- Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <script>
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            "primary": "#18217b",
            "accent-gold": "#D4AF37",
            "background-light": "#f6f6f8",
            "background-dark": "#121320",
          },
          fontFamily: {
            "display": ["Lexend", "sans-serif"]
          },
          borderRadius: {
            "DEFAULT": "0.5rem",
            "lg": "1rem",
            "xl": "1.5rem",
            "full": "9999px"
          },
        },
      },
    };
  </script>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Lexend:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

  <!-- App CSS -->
  <link rel="stylesheet" href="/mobile/assets/css/mobile.css">

  <style>
    body { position: fixed; top: 0; left: 0; right: 0; bottom: 0; }
  </style>
</head>
<body>

<?php include $pageFile; ?>

<script src="/mobile/assets/js/mobile.js"></script>
</body>
</html>
