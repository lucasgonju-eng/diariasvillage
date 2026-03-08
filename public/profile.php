<?php
require_once __DIR__ . '/src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

$user = Helpers::requireAuthWeb();
$client = new SupabaseClient(new HttpClient());
$studentIdsScope = [];
$sessionStudentId = trim((string) ($user['student_id'] ?? ''));
if ($sessionStudentId !== '') {
    $studentIdsScope[$sessionStudentId] = true;
}
$documentDigits = preg_replace('/\D+/', '', (string) ($user['parent_document'] ?? '')) ?? '';

$userId = trim((string) ($user['id'] ?? ''));
if ($userId !== '') {
    $guardianCurrent = $client->select(
        'guardians',
        'select=student_id,parent_document&id=eq.' . urlencode($userId) . '&limit=1'
    );
    if (($guardianCurrent['ok'] ?? false) && !empty($guardianCurrent['data'][0])) {
        $current = $guardianCurrent['data'][0];
        $sid = trim((string) ($current['student_id'] ?? ''));
        if ($sid !== '') {
            $studentIdsScope[$sid] = true;
        }
        if ($documentDigits === '') {
            $documentDigits = preg_replace('/\D+/', '', (string) ($current['parent_document'] ?? '')) ?? '';
        }
    }
}

$userEmail = trim((string) ($user['email'] ?? ''));
if ($userEmail !== '') {
    $guardiansByEmail = $client->select(
        'guardians',
        'select=student_id&email=eq.' . urlencode($userEmail) . '&limit=200'
    );
    if (($guardiansByEmail['ok'] ?? false) && !empty($guardiansByEmail['data'])) {
        foreach ($guardiansByEmail['data'] as $row) {
            $sid = trim((string) ($row['student_id'] ?? ''));
            if ($sid !== '') {
                $studentIdsScope[$sid] = true;
            }
        }
    }
}

if ($documentDigits !== '') {
    $maskCpf = static function (string $digits): string {
        if (strlen($digits) !== 11) {
            return $digits;
        }
        return substr($digits, 0, 3) . '.'
            . substr($digits, 3, 3) . '.'
            . substr($digits, 6, 3) . '-'
            . substr($digits, 9, 2);
    };
    $cpfAttempts = [
        'parent_document=eq.' . urlencode($documentDigits) . '&select=student_id&limit=500',
        'parent_document=eq.' . urlencode($maskCpf($documentDigits)) . '&select=student_id&limit=500',
        'parent_document=ilike.' . urlencode('*' . $documentDigits . '*') . '&select=student_id&limit=500',
    ];
    foreach ($cpfAttempts as $query) {
        $guardiansByCpf = $client->select('guardians', $query);
        if (!($guardiansByCpf['ok'] ?? false) || empty($guardiansByCpf['data'])) {
            continue;
        }
        foreach ($guardiansByCpf['data'] as $row) {
            $sid = trim((string) ($row['student_id'] ?? ''));
            if ($sid !== '') {
                $studentIdsScope[$sid] = true;
            }
        }
    }
}

$studentRows = [];
$studentIds = array_keys($studentIdsScope);
if (!empty($studentIds)) {
    if (count($studentIds) === 1) {
        $studentResult = $client->select(
            'students',
            'select=id,name,enrollment,grade,class_name&id=eq.' . urlencode($studentIds[0]) . '&limit=1'
        );
    } else {
        $quotedStudentIds = array_map(static fn($id) => '"' . str_replace('"', '', $id) . '"', $studentIds);
        $studentResult = $client->select(
            'students',
            'select=id,name,enrollment,grade,class_name&id=in.(' . implode(',', $quotedStudentIds) . ')&order=name.asc&limit=20'
        );
    }
    if (($studentResult['ok'] ?? false) && !empty($studentResult['data']) && is_array($studentResult['data'])) {
        $studentRows = $studentResult['data'];
    }
}

$studentRow = [];
if (!empty($studentRows)) {
    if ($sessionStudentId !== '') {
        foreach ($studentRows as $candidate) {
            if (trim((string) ($candidate['id'] ?? '')) === $sessionStudentId) {
                $studentRow = $candidate;
                break;
            }
        }
    }
    if (empty($studentRow)) {
        $studentRow = $studentRows[0];
    }
}
$studentName = trim((string) ($studentRow['name'] ?? 'Aluno(a) não vinculado(a)'));
$studentEnrollment = trim((string) ($studentRow['enrollment'] ?? ''));
$studentGrade = trim((string) ($studentRow['grade'] ?? ''));
$studentClass = trim((string) (($studentRow['class_name'] ?? '') ?: ($studentRow['class'] ?? '')));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Perfil - Diárias Village</title>
  <meta name="description" content="Atualize os dados do responsável e mantenha o cadastro em dia." />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/style.css?v=5" />
</head>
<body>
  <header class="hero" id="top">
    <div class="container">
      <div class="topbar">
        <div class="brand">
          <span class="brand-mark" aria-hidden="true"></span>
          <div class="brand-text">
            <div class="brand-title">DIÁRIAS VILLAGE</div>
            <div class="brand-sub">Perfil do responsável</div>
          </div>
        </div>

        <div class="cta">
          <a class="btn btn-ghost btn-sm" href="/dashboard.php">Dashboard</a>
          <a class="btn btn-ghost btn-sm" href="/financeiro.php">Financeiro</a>
          <a class="btn btn-ghost btn-sm" href="/logout.php">Sair</a>
        </div>
      </div>

      <div class="hero-grid">
        <div class="hero-left">
          <div class="pill">Perfil</div>
          <h1>Atualize seus dados.</h1>
          <p class="lead">Mantenha o cadastro do responsável sempre atualizado.</p>

          <div class="microchips" role="list">
            <span class="microchip" role="listitem">Dados protegidos</span>
          <span class="microchip" role="listitem">Atualização rápida</span>
            <span class="microchip" role="listitem">Acesso seguro</span>
          </div>
        </div>

        <aside class="hero-card" aria-label="Formulario do perfil">
          <h3>Editar perfil</h3>
          <p class="muted">Atualize os dados do responsável.</p>

          <div style="margin-bottom:14px;padding:12px 14px;border:1px solid #D6B25E;border-radius:12px;background:#FFF9EA;">
            <div style="font-size:12px;font-weight:800;letter-spacing:.04em;color:#8A671E;margin-bottom:6px;">DADOS DO ALUNO(A)</div>
            <div style="font-size:16px;font-weight:800;color:#1A2133;margin-bottom:6px;">
              <?php echo htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div class="muted" style="font-size:13px;">
              <?php if ($studentEnrollment !== ''): ?>Matrícula: <strong><?php echo htmlspecialchars($studentEnrollment, ENT_QUOTES, 'UTF-8'); ?></strong><?php endif; ?>
              <?php if ($studentGrade !== ''): ?> <?php if ($studentEnrollment !== ''): ?>•<?php endif; ?> Série: <strong><?php echo htmlspecialchars($studentGrade, ENT_QUOTES, 'UTF-8'); ?></strong><?php endif; ?>
              <?php if ($studentClass !== ''): ?> <?php if ($studentEnrollment !== '' || $studentGrade !== ''): ?>•<?php endif; ?> Turma: <strong><?php echo htmlspecialchars($studentClass, ENT_QUOTES, 'UTF-8'); ?></strong><?php endif; ?>
            </div>
          </div>

          <form id="profile-form">
            <div class="form-group">
              <label>Nome do responsável</label>
              <input id="parent-name" value="<?php echo htmlspecialchars($user['parent_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            </div>
            <div class="form-group">
              <label>Telefone</label>
              <input id="parent-phone" value="<?php echo htmlspecialchars($user['parent_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            </div>
            <div class="form-group">
              <label>CPF/CNPJ</label>
              <input id="parent-document" value="<?php echo htmlspecialchars($user['parent_document'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            </div>

            <div class="grid-2">
              <div class="form-group">
                <label>Nova senha</label>
                <input type="password" id="new-password" />
              </div>
              <div class="form-group">
                <label>Confirmar nova senha</label>
                <input type="password" id="new-password-confirm" />
              </div>
            </div>

            <button class="btn btn-primary btn-block" type="submit">Salvar</button>
            <div id="profile-message"></div>
          </form>

          <hr style="border:none;border-top:1px solid #E6E9F2;margin:16px 0;">
          <h3 style="margin-top:0;">Adicionar mais um responsável</h3>
          <p class="muted">Cadastre outro responsável para o mesmo aluno.</p>
          <form id="add-guardian-form">
            <div class="form-group">
              <label>Nome do responsável</label>
              <input id="extra-parent-name" required />
            </div>
            <div class="form-group">
              <label>E-mail do responsável</label>
              <input type="email" id="extra-parent-email" required />
            </div>
            <div class="form-group">
              <label>Telefone</label>
              <input id="extra-parent-phone" />
            </div>
            <div class="form-group">
              <label>CPF/CNPJ</label>
              <input id="extra-parent-document" />
            </div>
            <button class="btn btn-primary btn-block" type="submit">Adicionar responsável</button>
            <div id="add-guardian-message"></div>
          </form>
          <div style="margin-top:12px;">
            <p class="muted" style="margin-bottom:8px;">Responsáveis cadastrados para este aluno</p>
            <div id="guardians-list" class="muted">Carregando...</div>
          </div>
        </aside>
      </div>
    </div>

    <svg class="wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
      <path d="M0,64 C240,120 480,120 720,72 C960,24 1200,24 1440,72 L1440,120 L0,120 Z"></path>
    </svg>
  </header>

  <footer class="footer">
    <div class="container">
      Desenvolvido por Lucas Gonçalves Junior - 2026
      <a class="tinyLink" href="/admin/" aria-label="Acesso administrativo">Admin</a>
    </div>
  </footer>

  <script src="/assets/js/profile.js?v=2"></script>
</body>
</html>
