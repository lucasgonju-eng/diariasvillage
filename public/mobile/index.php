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
    $appFile = __DIR__ . '/app/' . $safeRoute . '.php';
    if (!file_exists($appFile)) {
        header('Location: /mobile/?r=login');
        exit;
    }

    $appTitles = [
        'login'           => 'Login – Diárias Village',
        'primeiro-acesso' => 'Primeiro Acesso – Diárias Village',
        'grade'           => 'Workshops – Diárias Village',
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
    ];

    $page = $_GET['page'] ?? 'tela_inicial';
    if (!in_array($page, $allowed, true)) {
        $page = 'tela_inicial';
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

  <!-- PWA / iOS -->
  <meta name="theme-color" content="#0F2A75">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Village">
  <link rel="manifest" href="/mobile/manifest.json">
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
