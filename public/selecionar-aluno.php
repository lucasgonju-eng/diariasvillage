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

use App\GuardianAccountIdentity;
use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

$user = Helpers::requireAuthWeb(true);
$client = new SupabaseClient(new HttpClient());
$authUserId = trim((string) ($user['auth_user_id'] ?? ''));

if ($authUserId === '') {
    $_SESSION = [];
    session_destroy();
    header('Location: /login.php?reauth=1');
    exit;
}

$guardianResult = $client->selectAll(
    'guardians',
    'select=*&auth_user_id=eq.' . rawurlencode($authUserId) . '&order=id.asc'
);
$guardians = (($guardianResult['ok'] ?? false) && is_array($guardianResult['data'] ?? null))
    ? array_values(array_filter($guardianResult['data'], 'is_array'))
    : [];
$currentGuardianId = trim((string) ($user['id'] ?? ''));
$identity = GuardianAccountIdentity::analyze($guardians, $currentGuardianId);
if (
    $currentGuardianId === ''
    || ($identity['code'] ?? '') === 'GUARDIAN_SELECTION_MISMATCH'
) {
    $_SESSION = [];
    session_destroy();
    header('Location: /login.php?reauth=1');
    exit;
}

$pageError = '';
$studentIds = [];
if (
    !($identity['ok'] ?? false)
    || ($identity['mode'] ?? '') !== 'supabase_auth'
    || !hash_equals($authUserId, (string) ($identity['auth_user_id'] ?? ''))
) {
    $pageError = 'Não foi possível confirmar com segurança os filhos desta conta. Procure a secretaria.';
} else {
    foreach ($guardians as $guardian) {
        $studentId = trim((string) ($guardian['student_id'] ?? ''));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $studentId)) {
            $pageError = 'A conta familiar possui um vínculo incompleto. Procure a secretaria.';
            $studentIds = [];
            break;
        }
        $studentIds[$studentId] = true;
    }
}

if ($pageError === '' && count($studentIds) <= 1) {
    $_SESSION['family_student_selection_required'] = false;
    $_SESSION['family_student_selection_confirmed'] = true;
    $_SESSION['family_student_selected_at'] = time();
    header('Location: /dashboard.php');
    exit;
}

$students = [];
$monthlyPlansByStudent = [];
if ($pageError === '') {
    $quotedIds = array_map(
        static fn (string $id): string => '"' . str_replace('"', '', $id) . '"',
        array_keys($studentIds)
    );
    $studentResult = $client->selectAll(
        'students',
        'select=id,name,enrollment,grade,class_name&id=in.(' . implode(',', $quotedIds) . ')&order=name.asc'
    );
    if (!($studentResult['ok'] ?? false) || !is_array($studentResult['data'] ?? null)) {
        $pageError = 'Não foi possível carregar os alunos desta conta. Tente novamente.';
    } else {
        $students = array_values(array_filter($studentResult['data'], 'is_array'));
        $loadedIds = [];
        foreach ($students as $student) {
            $loadedIds[trim((string) ($student['id'] ?? ''))] = true;
        }
        if (array_diff_key($studentIds, $loadedIds) !== []) {
            $pageError = 'Um dos vínculos de aluno não foi encontrado. Procure a secretaria.';
            $students = [];
        }
    }

    if ($pageError === '') {
        $planResult = $client->selectAll(
            'monthly_student_plans',
            'select=student_id,weekly_days,status&status=eq.active&order=student_id.asc'
        );
        if (!($planResult['ok'] ?? false) || !is_array($planResult['data'] ?? null)) {
            $pageError = 'Não foi possível confirmar os planos dos alunos. Tente novamente.';
            $students = [];
        } else {
            foreach ($planResult['data'] as $plan) {
                if (!is_array($plan)) {
                    continue;
                }
                $studentId = trim((string) ($plan['student_id'] ?? ''));
                if (isset($studentIds[$studentId])) {
                    $monthlyPlansByStudent[$studentId] = $plan;
                }
            }
        }
    }
}

$_SESSION['family_student_selection_required'] = true;
$_SESSION['family_student_selection_confirmed'] = false;
$_SESSION['family_student_count'] = count($studentIds);
if (empty($_SESSION['family_selection_csrf'])) {
    $_SESSION['family_selection_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['family_selection_csrf'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Escolha o filho - Diárias Village</title>
  <meta name="description" content="Escolha para qual filho será feito o planejamento no Village." />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/style.css?v=5" />
  <style>
    body { background: #071431; }
    .family-choice-shell { min-height: 100vh; padding: 32px 16px 56px; color: #fff; }
    .family-choice-wrap { width: min(920px, 100%); margin: 0 auto; }
    .family-choice-brand { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 32px; }
    .family-choice-alert { background: #ffd35a; color: #111827; border: 4px solid #fff; border-radius: 18px; padding: 14px 18px; font-weight: 900; letter-spacing: .08em; text-align: center; box-shadow: 0 12px 32px rgba(0,0,0,.28); }
    .family-choice-title { max-width: 780px; margin: 28px auto 12px; font-size: clamp(2rem, 6vw, 4rem); line-height: .98; text-align: center; font-weight: 900; text-transform: uppercase; }
    .family-choice-lead { max-width: 680px; margin: 0 auto 30px; color: #dbe7ff; font-size: 1.08rem; line-height: 1.6; text-align: center; }
    .family-choice-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px; }
    .family-choice-form { margin: 0; }
    .family-choice-card { width: 100%; min-height: 235px; padding: 24px; border: 3px solid #d6b25e; border-radius: 22px; background: #fff; color: #0b1739; text-align: left; cursor: pointer; box-shadow: 0 16px 34px rgba(0,0,0,.25); transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease; }
    .family-choice-card:hover, .family-choice-card:focus-visible { transform: translateY(-5px); border-color: #ffd35a; box-shadow: 0 20px 42px rgba(0,0,0,.34); outline: 4px solid rgba(255,211,90,.35); }
    .family-choice-name { display: block; margin-top: 13px; font-size: 1.65rem; line-height: 1.08; font-weight: 900; }
    .family-choice-meta { display: block; margin-top: 10px; color: #536079; font-weight: 700; }
    .family-choice-plan { display: inline-flex; margin-top: 20px; padding: 8px 12px; border-radius: 999px; background: #e7efff; color: #153b82; font-weight: 900; }
    .family-choice-plan.monthly { background: #dcfce7; color: #166534; }
    .family-choice-action { display: block; margin-top: 18px; color: #0a52c7; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; }
    .family-choice-error { margin-top: 24px; padding: 22px; border-radius: 18px; background: #fee2e2; color: #991b1b; font-weight: 800; text-align: center; }
    .family-choice-footnote { margin: 28px auto 0; max-width: 700px; color: #bfcee9; text-align: center; line-height: 1.55; }
    @media (max-width: 620px) {
      .family-choice-shell { padding-top: 20px; }
      .family-choice-brand { align-items: flex-start; }
      .family-choice-title { margin-top: 22px; }
      .family-choice-card { min-height: 210px; }
    }
  </style>
</head>
<body>
  <main class="family-choice-shell">
    <div class="family-choice-wrap">
      <div class="family-choice-brand">
        <div>
          <div class="brand-title">DIÁRIAS VILLAGE</div>
          <div class="brand-sub">Conta familiar</div>
        </div>
        <a class="btn btn-ghost btn-sm" href="/logout.php">Sair</a>
      </div>

      <div class="family-choice-alert">ESCOLHA O ALUNO ANTES DE CONTINUAR</div>
      <h1 class="family-choice-title">Para qual filho é este planejamento?</h1>
      <p class="family-choice-lead">
        Esta escolha define as oficinas, o day-use e qualquer cobrança exibida a seguir.
        Nenhum filho é escolhido automaticamente.
      </p>

      <?php if ($pageError !== ''): ?>
        <div class="family-choice-error" role="alert">
          <?php echo htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php else: ?>
        <div class="family-choice-grid" aria-label="Filhos vinculados à conta">
          <?php foreach ($students as $student): ?>
            <?php
              $studentId = trim((string) ($student['id'] ?? ''));
              $studentName = trim((string) ($student['name'] ?? 'Aluno(a)'));
              $enrollment = trim((string) ($student['enrollment'] ?? ''));
              $grade = trim((string) ($student['grade'] ?? ''));
              $className = trim((string) ($student['class_name'] ?? ''));
              $plan = $monthlyPlansByStudent[$studentId] ?? null;
            ?>
            <form class="family-choice-form" method="post" action="/api/select-student.php">
              <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($studentId, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
              <button class="family-choice-card" type="submit">
                <span class="pill">Escolher este aluno</span>
                <span class="family-choice-name"><?php echo htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="family-choice-meta">
                  <?php echo htmlspecialchars(
                    ($enrollment !== '' ? 'Matrícula ' . $enrollment : 'Matrícula não informada')
                      . (($grade !== '' || $className !== '') ? ' • ' . trim($grade . ' ' . $className) : ''),
                    ENT_QUOTES,
                    'UTF-8'
                  ); ?>
                </span>
                <?php if (is_array($plan)): ?>
                  <span class="family-choice-plan monthly">
                    Mensalista • <?php echo (int) ($plan['weekly_days'] ?? 0); ?> dias por semana
                  </span>
                  <span class="family-choice-action">Continuar para as oficinas mensais →</span>
                <?php else: ?>
                  <span class="family-choice-plan">Day-use</span>
                  <span class="family-choice-action">Continuar para planejar a diária →</span>
                <?php endif; ?>
              </button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <p class="family-choice-footnote">
        Quer planejar para outro filho depois? Use o botão <strong>Trocar filho</strong> no topo do painel.
      </p>
    </div>
  </main>
</body>
</html>
