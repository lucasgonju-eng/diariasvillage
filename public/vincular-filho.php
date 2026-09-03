<?php
$bootstrapCandidates = [
    __DIR__ . '/src/Bootstrap.php',
    dirname(__DIR__) . '/src/Bootstrap.php',
];
foreach ($bootstrapCandidates as $bootstrapFile) {
    if (is_file($bootstrapFile)) {
        require_once $bootstrapFile;
        break;
    }
}

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

$user = Helpers::requireAuthWeb();
$authUserId = trim((string) ($user['auth_user_id'] ?? ''));
if ($authUserId === '') {
    $_SESSION['dashboard_error'] = 'A conta precisa estar vinculada ao acesso principal para solicitar outro filho.';
    header('Location: /dashboard.php');
    exit;
}

$client = new SupabaseClient(new HttpClient());
$requestsResult = $client->selectAll(
    'family_link_requests',
    'select=id,requested_enrollment,target_student_id,status,requested_at,reviewed_at,review_note'
        . '&requester_auth_user_id=eq.' . rawurlencode($authUserId)
        . '&order=requested_at.desc'
);
$requests = (($requestsResult['ok'] ?? false) && is_array($requestsResult['data'] ?? null))
    ? array_values(array_filter($requestsResult['data'], 'is_array'))
    : [];

$targetIds = [];
foreach ($requests as $request) {
    $targetId = trim((string) ($request['target_student_id'] ?? ''));
    if ($targetId !== '') {
        $targetIds[$targetId] = true;
    }
}
$studentsById = [];
if ($targetIds !== []) {
    $quotedIds = array_map(
        static fn (string $id): string => '"' . str_replace('"', '', $id) . '"',
        array_keys($targetIds)
    );
    $studentsResult = $client->selectAll(
        'students',
        'select=id,name,enrollment&id=in.(' . implode(',', $quotedIds) . ')&order=name.asc'
    );
    if (($studentsResult['ok'] ?? false) && is_array($studentsResult['data'] ?? null)) {
        foreach ($studentsResult['data'] as $student) {
            if (is_array($student)) {
                $studentsById[(string) ($student['id'] ?? '')] = $student;
            }
        }
    }
}

$_SESSION['family_link_request_csrf'] = bin2hex(random_bytes(32));
$csrfToken = (string) $_SESSION['family_link_request_csrf'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Adicionar outro filho - Diárias Village</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/style.css?v=5" />
  <style>
    body { background: #f3f6fb; color: #0b1739; }
    .link-child-shell { min-height: 100vh; padding: 28px 16px 56px; }
    .link-child-wrap { width: min(780px, 100%); margin: 0 auto; }
    .link-child-head { padding: 26px; border-radius: 22px; background: #081b42; color: #fff; box-shadow: 0 14px 34px rgba(7,20,49,.2); }
    .link-child-head h1 { margin: 10px 0 8px; font-size: clamp(1.9rem, 5vw, 3rem); line-height: 1.05; }
    .link-child-head p { margin: 0; color: #d8e5ff; line-height: 1.6; }
    .link-child-card { margin-top: 20px; padding: 24px; border: 1px solid #dfe6f2; border-radius: 20px; background: #fff; box-shadow: 0 10px 26px rgba(7,20,49,.08); }
    .link-child-card h2 { margin: 0 0 8px; }
    .link-child-note { padding: 14px 16px; border-left: 5px solid #d6b25e; border-radius: 10px; background: #fff8df; line-height: 1.55; }
    .link-child-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
    .link-child-status { display: grid; gap: 10px; margin-top: 14px; }
    .link-child-status-item { padding: 14px; border-radius: 12px; background: #f3f6fb; }
    .link-child-status-item strong { display: block; margin-bottom: 4px; }
    .status-pending { color: #8a5b00; }
    .status-approved { color: #166534; }
    .status-rejected, .status-blocked { color: #991b1b; }
  </style>
</head>
<body>
  <main class="link-child-shell">
    <div class="link-child-wrap">
      <section class="link-child-head">
        <div class="pill">Conta familiar</div>
        <h1>Adicionar outro filho</h1>
        <p>
          Informe a matrícula. A secretaria confere o vínculo antes de liberar qualquer dado,
          oficina, diária ou cobrança desse aluno.
        </p>
      </section>

      <section class="link-child-card">
        <h2>Solicitar vínculo</h2>
        <div class="link-child-note">
          A solicitação não concede acesso automaticamente. Depois da aprovação, o filho aparecerá
          no menu obrigatório exibido a cada login.
        </div>
        <form id="family-link-request-form" style="margin-top:18px;">
          <div class="form-group">
            <label for="family-link-enrollment">Matrícula do outro filho</label>
            <input id="family-link-enrollment" type="text" maxlength="80" autocomplete="off" required />
          </div>
          <button class="btn btn-primary" type="submit">Enviar para a secretaria</button>
          <div id="family-link-message" class="charge-message" role="status"></div>
        </form>
      </section>

      <?php if ($requests !== []): ?>
        <section class="link-child-card">
          <h2>Solicitações anteriores</h2>
          <div class="link-child-status">
            <?php foreach ($requests as $request): ?>
              <?php
                $targetId = (string) ($request['target_student_id'] ?? '');
                $student = $studentsById[$targetId] ?? [];
                $status = strtoupper((string) ($request['status'] ?? 'PENDING'));
                $requestedEnrollment = trim((string) ($request['requested_enrollment'] ?? ''));
                $statusLabels = [
                    'PENDING' => 'Aguardando a secretaria',
                    'APPROVED' => 'Vínculo aprovado',
                    'REJECTED' => 'Solicitação rejeitada',
                    'BLOCKED' => 'Bloqueada para revisão',
                ];
              ?>
              <div class="link-child-status-item">
                <strong>
                  <?php echo htmlspecialchars(
                    ($status === 'APPROVED' && $student !== [])
                      ? (string) ($student['name'] ?? 'Aluno vinculado')
                        . (!empty($student['enrollment']) ? ' • Matrícula ' . (string) $student['enrollment'] : '')
                      : 'Matrícula informada: ' . ($requestedEnrollment !== '' ? $requestedEnrollment : '-'),
                    ENT_QUOTES,
                    'UTF-8'
                  ); ?>
                </strong>
                <span class="status-<?php echo strtolower($status); ?>">
                  <?php echo htmlspecialchars($statusLabels[$status] ?? $status, ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <div class="link-child-actions">
        <a class="btn btn-ghost" href="/dashboard.php">Voltar ao painel</a>
        <a class="btn btn-primary" href="/selecionar-aluno.php?trocar=1">Trocar filho</a>
      </div>
    </div>
  </main>
  <script>
    (() => {
      const form = document.querySelector('#family-link-request-form');
      const message = document.querySelector('#family-link-message');
      if (!form || !message) return;
      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        const enrollment = document.querySelector('#family-link-enrollment')?.value?.trim() || '';
        button.disabled = true;
        message.textContent = 'Enviando solicitação...';
        message.className = 'charge-message';
        try {
          const response = await fetch('/api/family-link-request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              enrollment,
              csrf_token: <?php echo json_encode($csrfToken); ?>,
            }),
          });
          const data = await response.json();
          if (!response.ok || !data.ok) {
            throw new Error(data.error || 'Não foi possível enviar a solicitação.');
          }
          message.textContent = data.message;
          message.className = 'charge-message success';
          form.reset();
          setTimeout(() => window.location.reload(), 900);
        } catch (error) {
          message.textContent = error?.message || 'Não foi possível enviar a solicitação.';
          message.className = 'charge-message error';
          button.disabled = false;
        }
      });
    })();
  </script>
</body>
</html>
