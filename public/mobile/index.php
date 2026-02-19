<?php
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?= htmlspecialchars($title) ?></title>

  <!-- PWA -->
  <meta name="theme-color" content="#0F2A75">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="manifest" href="/mobile/manifest.webmanifest">
  <link rel="apple-touch-icon" href="/mobile/assets/img/icon-192.png">

  <!-- Tailwind CDN (mesma config do Stitch) -->
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
  <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

  <!-- App CSS -->
  <link rel="stylesheet" href="/mobile/assets/css/mobile.css">

  <style>
    body { min-height: max(884px, 100dvh); }
  </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 min-h-screen flex flex-col">

<?php include $pageFile; ?>

<script src="/mobile/assets/js/mobile.js"></script>
</body>
</html>
