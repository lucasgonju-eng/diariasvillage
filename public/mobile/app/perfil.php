<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user'])) {
    header('Location: /mobile/?r=login');
    exit;
}
$user = $_SESSION['user'];
$nome = isset($user['parent_name']) ? trim((string) $user['parent_name']) : 'Responsável';
$email = isset($user['email']) ? trim((string) $user['email']) : '';
?>
<style>
  .m{--primary:#18217b;--gold:#D6B25E;--bg:#F4F6FB;--ink:#0B1020;--muted:#556070;--line:#E6E9EF;font-family:Inter,system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;min-height:100dvh;display:flex;flex-direction:column;padding:env(safe-area-inset-top,0) 0 env(safe-area-inset-bottom,0)}
  .m *{box-sizing:border-box}
  .m-head{background:linear-gradient(180deg,#0B2B63,#07162F);color:#fff;padding:48px 24px 32px;text-align:center}
  .m-head h1{margin:0 0 6px;font-size:24px;font-weight:800}
  .m-head p{margin:0;opacity:.8;font-size:14px}
  .m-body{flex:1;padding:24px 20px 32px;max-width:480px;width:100%;margin:0 auto}
  .m-card{background:#fff;border-radius:18px;padding:24px 20px;box-shadow:0 8px 30px rgba(0,0,0,.08);border:1px solid var(--line);margin-bottom:16px}
  .m-card h2{margin:0 0 12px;font-size:18px;font-weight:800}
  .m-card .line{margin:8px 0;font-size:14px;color:var(--ink)}
  .m-card .line strong{display:inline-block;min-width:80px;color:var(--muted)}
  .m-btn{display:block;width:100%;padding:16px;border:none;border-radius:14px;font-size:16px;font-weight:800;font-family:inherit;cursor:pointer;transition:transform .1s;-webkit-tap-highlight-color:transparent;text-align:center;text-decoration:none;color:#fff;background:var(--primary)}
  .m-btn:active{transform:scale(.97)}
  .m-link-back{display:inline-block;margin-top:16px;font-size:14px;font-weight:700;color:var(--primary);text-decoration:none}
</style>

<div class="m">
  <div class="m-head">
    <h1>Perfil</h1>
    <p>Seus dados no Diárias Village</p>
  </div>

  <div class="m-body">
    <div class="m-card">
      <h2>Dados do responsável</h2>
      <div class="line"><strong>Nome</strong> <?= htmlspecialchars($nome) ?></div>
      <?php if ($email !== ''): ?>
      <div class="line"><strong>E-mail</strong> <?= htmlspecialchars($email) ?></div>
      <?php endif; ?>
      <a href="/profile.php" class="m-btn" style="margin-top:16px">Editar perfil no site</a>
      <a href="/mobile/?r=grade" class="m-link-back">← Voltar ao dashboard</a>
    </div>
  </div>
</div>
