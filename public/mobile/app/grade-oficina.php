<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user'])) {
    header('Location: /mobile/?r=login');
    exit;
}
$diariaId = isset($_GET['diariaId']) ? trim((string) $_GET['diariaId']) : '';
if ($diariaId === '' || !preg_match('/^[a-f0-9\-]+$/i', $diariaId)) {
    header('Location: /mobile/?r=grade');
    exit;
}
$gradeUrl = '/diaria-grade-oficina-modular.php?diariaId=' . rawurlencode($diariaId);
?>
<style>
  .m{--primary:#18217b;--gold:#D6B25E;--gold2:#E2C377;--bg:#F4F6FB;--ink:#0B1020;--muted:#556070;--line:#E6E9EF;font-family:Inter,system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;min-height:100dvh;display:flex;flex-direction:column;padding:env(safe-area-inset-top,0) 0 env(safe-area-inset-bottom,0)}
  .m *{box-sizing:border-box}
  .m-head{background:linear-gradient(180deg,#0B2B63,#07162F);color:#fff;padding:48px 24px 32px;text-align:center}
  .m-head h1{margin:0 0 6px;font-size:24px;font-weight:800}
  .m-head p{margin:0;opacity:.8;font-size:14px}
  .m-body{flex:1;padding:24px 20px 32px;max-width:480px;width:100%;margin:0 auto}
  .m-card{background:#fff;border-radius:18px;padding:24px 20px;box-shadow:0 8px 30px rgba(0,0,0,.08);border:1px solid var(--line);margin-bottom:16px}
  .m-card h2{margin:0 0 6px;font-size:18px;font-weight:800}
  .m-card p{margin:0 0 16px;color:var(--muted);font-size:14px;line-height:1.5}
  .m-btn{display:block;width:100%;padding:16px;border:none;border-radius:14px;font-size:16px;font-weight:800;font-family:inherit;cursor:pointer;transition:transform .1s;-webkit-tap-highlight-color:transparent;text-align:center;text-decoration:none;color:inherit}
  .m-btn:active{transform:scale(.97)}
  .m-btn-gold{background:linear-gradient(180deg,var(--gold2),var(--gold));color:#141414;box-shadow:0 8px 24px rgba(214,178,94,.3)}
  .m-link-back{display:inline-block;margin-top:16px;font-size:14px;font-weight:700;color:var(--primary);text-decoration:none}
</style>

<div class="m">
  <div class="m-head">
    <h1>Grade de Oficina Modular</h1>
    <p>Monte a grade e conclua o pagamento</p>
  </div>

  <div class="m-body">
    <div class="m-card">
      <h2>Próximo passo</h2>
      <p>Clique no botão abaixo para abrir a tela de seleção de oficinas e finalizar o pagamento via PIX.</p>
      <a href="<?= htmlspecialchars($gradeUrl) ?>" class="m-btn m-btn-gold">Abrir grade de oficinas</a>
      <a href="/mobile/?r=grade" class="m-link-back">← Voltar ao dashboard</a>
    </div>
  </div>
</div>
