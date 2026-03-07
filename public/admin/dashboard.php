<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\MonthlyStudents;
use App\SupabaseClient;

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    header('Location: /admin/');
    exit;
}
$canViewAsUser = (($_SESSION['admin_user'] ?? '') === 'admin');
$canMergeDuplicates = (($_SESSION['admin_user'] ?? '') === 'admin');
$allowedTabs = ['charges', 'inadimplentes', 'recebidas', 'sem-whatsapp', 'pendencias', 'mensalistas', 'exclusoes', 'reset-senha', 'fluxo-caixa', 'dados-asaas', 'entries'];
if ($canMergeDuplicates) {
    $allowedTabs[] = 'duplicados';
}
$activeTab = trim((string) ($_GET['tab'] ?? 'charges'));
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'charges';
}

$client = new SupabaseClient(new HttpClient());
$paymentsResult = $client->select(
    'payments',
    'select=*,students(name,enrollment),guardians(parent_name,email)&status=eq.paid&order=paid_at.desc&limit=200'
);
$payments = $paymentsResult['data'] ?? [];

$queuedPendingResult = $client->select(
    'payments',
    'select=*,students(name,enrollment),guardians(parent_name,email,parent_phone)&billing_type=eq.PIX_MANUAL_QUEUE&status=eq.queued&order=created_at.desc&limit=500'
);
$queuedPending = $queuedPendingResult['data'] ?? [];

$allUnpaidResult = $client->select(
    'payments',
    'select=*,students(name,enrollment),guardians(parent_name,email,parent_phone)&paid_at=is.null&order=created_at.desc&limit=20000'
);
$allUnpaidRows = $allUnpaidResult['data'] ?? [];
$manualPending = [];
foreach ($allUnpaidRows as $row) {
    $status = strtolower(trim((string) ($row['status'] ?? '')));
    if (in_array($status, ['paid', 'canceled', 'refunded', 'deleted'], true)) {
        continue;
    }
    if ($status === 'queued' && strtoupper((string) ($row['billing_type'] ?? '')) === 'PIX_MANUAL_QUEUE') {
        continue; // Já mostrado na seção de fila.
    }
    $manualPending[] = $row;
}

$monthlyItems = MonthlyStudents::load();
$monthlyById = MonthlyStudents::mapByStudentId($monthlyItems);
$monthlyByName = MonthlyStudents::mapByNormalizedName($monthlyItems);
$monthlyRowsForJs = array_values(array_map(static function (array $row): array {
    return [
        'student_id' => (string) ($row['student_id'] ?? ''),
        'student_name' => (string) ($row['student_name'] ?? ''),
        'enrollment' => (string) ($row['enrollment'] ?? ''),
        'weekly_days' => (int) ($row['weekly_days'] ?? 0),
        'active' => ($row['active'] ?? true) !== false,
        'updated_at' => (string) ($row['updated_at'] ?? ''),
        'updated_by' => (string) ($row['updated_by'] ?? ''),
    ];
}, $monthlyItems));

$inadimplentesMonthlyMetaById = [];

$manualPaidResult = $client->select(
    'payments',
    'select=*,students(name,enrollment),guardians(parent_name,email,parent_phone)&status=eq.paid&order=paid_at.desc&limit=1000'
);
$manualPaid = $manualPaidResult['data'] ?? [];

$inadimplentesClassified = MonthlyStudents::classifyRowsByQuota(
    array_merge($queuedPending, $manualPending, $manualPaid),
    static function (array $row): array {
        $student = is_array($row['students'] ?? null) ? $row['students'] : [];
        $studentId = trim((string) ($row['student_id'] ?? ($student['id'] ?? '')));
        $studentName = trim((string) ($student['name'] ?? ''));
        $dates = MonthlyStudents::extractDatesFromPayment(
            (string) ($row['daily_type'] ?? ''),
            (string) ($row['payment_date'] ?? '')
        );
        return [
            'student_id' => $studentId,
            'student_name' => $studentName,
            'dates' => $dates,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    },
    $monthlyById,
    $monthlyByName
);
$inadimplentesVisibleById = [];
foreach (($inadimplentesClassified['visible'] ?? []) as $rowVisible) {
    if (!is_array($rowVisible)) {
        continue;
    }
    $pid = trim((string) ($rowVisible['id'] ?? ''));
    if ($pid !== '') {
        $inadimplentesVisibleById[$pid] = true;
    }
}
foreach (($inadimplentesClassified['meta'] ?? []) as $metaId => $metaRow) {
    if (!is_array($metaRow) || empty($metaRow['monthly'])) {
        continue;
    }
    if (!empty($metaRow['overflow_dates'])) {
        $inadimplentesMonthlyMetaById[(string) $metaId] = $metaRow;
    }
}
$queuedPending = array_values(array_filter($queuedPending, static function ($row) use ($inadimplentesVisibleById): bool {
    $pid = trim((string) ($row['id'] ?? ''));
    return $pid === '' || isset($inadimplentesVisibleById[$pid]);
}));
$manualPending = array_values(array_filter($manualPending, static function ($row) use ($inadimplentesVisibleById): bool {
    $pid = trim((string) ($row['id'] ?? ''));
    return $pid === '' || isset($inadimplentesVisibleById[$pid]);
}));

$missingWhatsappResult = $client->select(
    'guardians',
    'select=parent_name,email,parent_phone,parent_document,students(name,enrollment)&or=(parent_phone.is.null,parent_phone.eq.)&order=created_at.desc&limit=500'
);
$missingWhatsapp = $missingWhatsappResult['data'] ?? [];

$pendenciasResult = $client->select(
    'pendencia_de_cadastro',
    'select=id,student_name,student_id,guardian_name,guardian_cpf,guardian_email,created_at,paid_at,payment_date,access_code,enrollment,asaas_payment_id,asaas_invoice_url&order=created_at.desc&limit=500'
);
if (!($pendenciasResult['ok'] ?? false)) {
    // Fallback para ambientes com schema antigo sem campos asaas_*.
    $pendenciasResult = $client->select(
        'pendencia_de_cadastro',
        'select=id,student_name,student_id,guardian_name,guardian_cpf,guardian_email,created_at,paid_at,payment_date,access_code,enrollment&order=created_at.desc&limit=500'
    );
}
$pendenciasAll = $pendenciasResult['data'] ?? [];
$pendenciasPagas = array_filter($pendenciasAll, fn($p) => !empty($p['paid_at']));
$pendencias = array_values(array_filter($pendenciasAll, static function ($p): bool {
    return empty($p['paid_at']);
}));
$valorPendencia = 77.00;

$normalizeSortName = static function (string $name): string {
    $name = trim($name);
    if ($name === '') {
        return '';
    }
    if (function_exists('mb_strtoupper')) {
        $name = mb_strtoupper($name, 'UTF-8');
    } else {
        $name = strtoupper($name);
    }
    $translit = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
    if ($translit !== false) {
        $name = $translit;
    }
    $name = preg_replace('/[^A-Z0-9 ]+/', '', $name) ?? '';
    return trim($name);
};

$sortByStudentName = static function (array &$items, callable $resolver) use ($normalizeSortName): void {
    usort($items, static function ($a, $b) use ($resolver, $normalizeSortName): int {
        $aName = $normalizeSortName((string) $resolver($a));
        $bName = $normalizeSortName((string) $resolver($b));
        $cmp = strcmp($aName, $bName);
        if ($cmp !== 0) {
            return $cmp;
        }
        $aDate = strtotime((string) ($a['created_at'] ?? $a['paid_at'] ?? $a['payment_date'] ?? '')) ?: 0;
        $bDate = strtotime((string) ($b['created_at'] ?? $b['paid_at'] ?? $b['payment_date'] ?? '')) ?: 0;
        return $bDate <=> $aDate;
    });
};

$sortByStudentName($payments, static fn($row) => (string) (($row['students']['name'] ?? '') ?: ''));
$sortByStudentName($manualPending, static fn($row) => (string) (($row['students']['name'] ?? '') ?: ''));
$sortByStudentName($queuedPending, static fn($row) => (string) (($row['students']['name'] ?? '') ?: ''));
$sortByStudentName($manualPaid, static fn($row) => (string) (($row['students']['name'] ?? '') ?: ''));
$sortByStudentName($missingWhatsapp, static fn($row) => (string) (($row['students']['name'] ?? '') ?: ''));
$sortByStudentName($pendenciasPagas, static fn($row) => (string) ($row['student_name'] ?? ''));
$sortByStudentName($pendencias, static fn($row) => (string) ($row['student_name'] ?? ''));

$studentsResult = $client->select('students', 'select=id,name,enrollment,created_at,active&limit=10000');
$students = $studentsResult['data'] ?? [];
$studentsForJs = array_map(static function ($row): array {
    if (!is_array($row)) {
        return [
            'id' => '',
            'name' => '',
            'enrollment' => null,
            'grade' => null,
            'class_name' => null,
        ];
    }
    return [
        'id' => (string) ($row['id'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'enrollment' => $row['enrollment'] ?? null,
        'grade' => isset($row['grade']) ? (int) $row['grade'] : null,
        'class_name' => $row['class_name'] ?? null,
    ];
}, $students);
$duplicateGroups = [];
$duplicateEnrollmentGroups = [];
$cpfDuplicateGroups = [];
if ($students) {
    $normalizeName = static function (string $name): string {
        $name = mb_strtoupper($name, 'UTF-8');
        $translit = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
        if ($translit !== false) {
            $name = $translit;
        }
        $name = preg_replace('/[^A-Z0-9]+/', '', $name) ?? '';
        return trim($name);
    };

    $groups = [];
    foreach ($students as $student) {
        $key = $normalizeName($student['name'] ?? '');
        if ($key === '') {
            continue;
        }
        $groups[$key][] = $student;
    }
    foreach ($groups as $group) {
        if (count($group) > 1) {
            usort($group, static function ($a, $b) {
                return strtotime($a['created_at'] ?? '') <=> strtotime($b['created_at'] ?? '');
            });
            $duplicateGroups[] = $group;
        }
    }

    $enrollmentGroups = [];
    foreach ($students as $student) {
        $enrollment = trim((string) ($student['enrollment'] ?? ''));
        if ($enrollment === '' || $enrollment === '-') {
            continue;
        }
        $enrollmentGroups[$enrollment][] = $student;
    }
    foreach ($enrollmentGroups as $group) {
        if (count($group) > 1) {
            usort($group, static function ($a, $b) {
                return strtotime($a['created_at'] ?? '') <=> strtotime($b['created_at'] ?? '');
            });
            $duplicateEnrollmentGroups[] = $group;
        }
    }
}

$guardiansResult = $client->select(
    'guardians',
    'select=student_id,parent_document,students(id,name,enrollment)&parent_document=not.is.null&order=created_at.desc&limit=10000'
);
$guardians = $guardiansResult['data'] ?? [];
if ($guardians) {
    $cpfGroups = [];
    foreach ($guardians as $guardian) {
        $cpf = trim((string) ($guardian['parent_document'] ?? ''));
        if ($cpf === '') {
            continue;
        }
        $cpfGroups[$cpf][] = $guardian;
    }
    foreach ($cpfGroups as $cpf => $group) {
        $studentIds = array_unique(array_filter(array_map(static fn($g) => $g['student_id'] ?? '', $group)));
        if (count($studentIds) > 1) {
            $cpfDuplicateGroups[] = $group;
        }
    }
}

$exclusionsLog = [];
$exclusionsLogPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'exclusions_log.jsonl';
if (is_file($exclusionsLogPath)) {
    $lines = @file($exclusionsLogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $maxRows = 500;
    $count = 0;
    foreach (array_reverse($lines) as $line) {
        if ($count >= $maxRows) {
            break;
        }
        $decoded = json_decode((string) $line, true);
        if (!is_array($decoded)) {
            continue;
        }
        $exclusionsLog[] = [
            'deleted_at' => $decoded['deleted_at'] ?? '',
            'entity_type' => $decoded['entity_type'] ?? '',
            'entity_id' => $decoded['entity_id'] ?? '',
            'student_name' => $decoded['student_name'] ?? '',
            'guardian_name' => $decoded['guardian_name'] ?? '',
            'payment_date' => $decoded['payment_date'] ?? '',
            'amount' => $decoded['amount'] ?? null,
            'reason' => $decoded['reason'] ?? '',
            'source' => $decoded['source'] ?? '',
            'notes' => $decoded['notes'] ?? '',
        ];
        $count++;
    }
}
if (!empty($duplicateGroups)) {
    usort($duplicateGroups, static function (array $a, array $b) use ($normalizeSortName): int {
        $aName = $normalizeSortName((string) (($a[0]['name'] ?? '') ?: ''));
        $bName = $normalizeSortName((string) (($b[0]['name'] ?? '') ?: ''));
        return strcmp($aName, $bName);
    });
}
if (!empty($duplicateEnrollmentGroups)) {
    usort($duplicateEnrollmentGroups, static function (array $a, array $b) use ($normalizeSortName): int {
        $aName = $normalizeSortName((string) (($a[0]['name'] ?? '') ?: ''));
        $bName = $normalizeSortName((string) (($b[0]['name'] ?? '') ?: ''));
        return strcmp($aName, $bName);
    });
}
if (!empty($cpfDuplicateGroups)) {
    usort($cpfDuplicateGroups, static function (array $a, array $b) use ($normalizeSortName): int {
        $aName = $normalizeSortName((string) (($a[0]['students']['name'] ?? '') ?: ''));
        $bName = $normalizeSortName((string) (($b[0]['students']['name'] ?? '') ?: ''));
        return strcmp($aName, $bName);
    });
}
if (!empty($exclusionsLog)) {
    usort($exclusionsLog, static function (array $a, array $b) use ($normalizeSortName): int {
        $aName = $normalizeSortName((string) ($a['student_name'] ?? ''));
        $bName = $normalizeSortName((string) ($b['student_name'] ?? ''));
        $cmp = strcmp($aName, $bName);
        if ($cmp !== 0) {
            return $cmp;
        }
        $aDate = strtotime((string) ($a['deleted_at'] ?? '')) ?: 0;
        $bDate = strtotime((string) ($b['deleted_at'] ?? '')) ?: 0;
        return $bDate <=> $aDate;
    });
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin - Entradas</title>
  <link rel="stylesheet" href="/assets/style.css?v=5" />
  <style>
    .admin-wrap{max-width:1120px;margin:0 auto;padding:28px 20px}
    .admin-header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
    .admin-title{font-weight:800;letter-spacing:.08em;font-size:12px;color:#1a2133}
    .admin-header .btn.btn-ghost{
      color:#111827;
      border-color:#CBD5E1;
      background:#F8FAFC;
      font-weight:700;
    }
    .admin-header .btn.btn-ghost:hover{filter:brightness(.98)}
    .admin-card{background:#fff;border-radius:16px;padding:18px;border:1px solid var(--line);box-shadow:var(--shadow-soft)}
    .admin-tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px}
    .admin-tab{background:#EFF3FA;border-color:#D5DCE8;color:#1a2133}
    .admin-card{background:#FDFDFE;border-radius:16px;padding:18px;border:1px solid #E6EAF2;box-shadow:var(--shadow-soft)}
    .admin-table{width:100%;border-collapse:collapse}
    .admin-table th,.admin-table td{padding:10px 8px}
    .admin-table tr{border-top:1px solid #f1f5f9}
    .charge-list{display:grid;gap:12px;margin-top:12px}
    .charge-item{background:#F6F8FC;border:1px solid #E6E9F2;border-radius:14px;padding:14px}
    .charge-header{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}
    .charge-header .btn.btn-ghost{
      color:#111827;
      border-color:#CBD5E1;
      background:#F8FAFC;
      font-weight:700;
    }
    .charge-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
    .date-list{display:grid;gap:8px}
    .date-row{display:flex;gap:8px;align-items:center}
    .date-row input{flex:1}
    .date-row .btn{
      color:#0A1B4D;
      border-color:#BFD0EE;
      background:#E8F0FF;
      font-weight:800;
    }
    .btn-danger{
      color:#B91C1C;
      border-color:#FCA5A5;
      background:#FEE2E2;
      font-weight:800;
    }
    .btn-danger:hover{filter:brightness(.97)}
    .input-sm{
      width:140px;
      padding:6px 8px;
      border-radius:10px;
      border:1px solid #E6E9F2;
      font-size:12px;
      margin-right:8px;
    }
    .date-actions{display:flex;gap:6px}
    .charge-message{margin-top:12px;font-size:13px}
    .cashflow-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin:10px 0 14px}
    .cashflow-summary{display:flex;gap:10px;flex-wrap:wrap;margin:8px 0 14px}
    .cashflow-pill{background:#F3F6FB;border:1px solid #E2E8F5;border-radius:999px;padding:7px 12px;font-size:12px;color:#1a2133}
    .admin-view-user{display:flex;gap:8px;align-items:center}
    .admin-view-user input{width:220px}
    .view-user-form{margin:12px 0;padding:12px;border-radius:12px;background:#FFF7ED;border:1px solid #FED7AA}
    .pendencia-student-link{font-size:12px;line-height:1.4;color:#0F172A}
    .pendencia-student-link .pending{color:#B45309;font-weight:700}
    .pendencia-student-actions{display:grid;gap:8px;min-width:240px}
    .pendencia-student-actions input{min-width:220px}
    .pendencia-student-actions .btn{justify-content:center}
    .monthly-check-row{background:#E0F2FE}
    .monthly-check-badge{display:inline-block;margin-left:8px;padding:2px 8px;border-radius:999px;background:#0EA5E9;color:#fff;font-size:11px;font-weight:700}
    .monthly-days-wrap{display:flex;gap:12px;flex-wrap:wrap}
    .monthly-days-wrap label{display:flex;gap:6px;align-items:center;padding:6px 10px;border:1px solid #CBD5E1;border-radius:10px;background:#F8FAFC}
    .hidden{display:none}
  </style>
</head>
<body data-active-tab="<?php echo htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8'); ?>">
  <div class="admin-wrap">
    <header class="admin-header">
      <div class="admin-title">DIÁRIAS VILLAGE • ADMIN</div>
      <div class="cta">
        <?php if ($canViewAsUser): ?>
          <div class="admin-view-user">
            <input id="admin-view-user-student" list="admin-students-list" placeholder="Digite 3 letras do aluno" autocomplete="off" />
            <datalist id="admin-students-list"></datalist>
            <button id="admin-view-user-btn" class="btn btn-ghost btn-sm" type="button">Ver como usuário</button>
            <button id="admin-add-guardian-btn" class="btn btn-ghost btn-sm" type="button">Criar mais um responsável</button>
          </div>
        <?php endif; ?>
        <a class="btn btn-primary btn-sm" href="/admin/dashboard.php?tab=fluxo-caixa" data-tab="fluxo-caixa">Fluxo de Caixa</a>
        <a class="btn btn-danger btn-sm" href="/admin/settle-pendencia.php">Baixa manual</a>
        <a class="btn btn-ghost btn-sm" href="/admin/import.php">Importar alunos</a>
        <a class="btn btn-ghost btn-sm" href="/logout.php">Sair</a>
      </div>
    </header>
    <?php if ($canViewAsUser): ?>
      <div id="admin-view-user-form" class="view-user-form hidden">
        <div class="charge-fields">
          <div class="form-group">
            <label>Aluno</label>
            <input id="view-user-student-name" type="text" readonly />
          </div>
          <div class="form-group">
            <label>Nome do responsável</label>
            <input id="view-user-parent-name" type="text" placeholder="Nome completo" />
          </div>
          <div class="form-group">
            <label>E-mail do responsável</label>
            <input id="view-user-parent-email" type="email" placeholder="email@exemplo.com" />
          </div>
          <div class="form-group">
            <label>Telefone</label>
            <input id="view-user-parent-phone" type="text" placeholder="(DDD) 99999-9999" />
          </div>
          <div class="form-group">
            <label>CPF/CNPJ</label>
            <input id="view-user-parent-document" type="text" placeholder="Somente números" />
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;">
            <label style="display:flex;gap:8px;align-items:center;">
              <input id="view-user-force-create" type="checkbox" />
              Salvar como novo responsável
            </label>
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;gap:8px;">
            <button id="view-user-save-guardian" class="btn btn-danger btn-sm" type="button">Salvar responsável</button>
            <button id="view-user-cancel-guardian" class="btn btn-ghost btn-sm" type="button">Cancelar</button>
          </div>
        </div>
        <div id="view-user-form-message" class="charge-message"></div>
      </div>
    <?php endif; ?>

    <div class="admin-card">
      <div class="admin-tabs">
        <a class="btn btn-primary btn-sm" href="/admin/dashboard.php?tab=charges" data-tab="charges">Cobrança manual</a>
        <a class="btn btn-primary btn-sm" href="/admin/dashboard.php?tab=inadimplentes" data-tab="inadimplentes">Cobranças em aberto</a>
        <a class="btn btn-primary btn-sm" href="/admin/dashboard.php?tab=recebidas" data-tab="recebidas">Cobranças recebidas</a>
        <a class="btn btn-primary btn-sm" href="/admin/dashboard.php?tab=sem-whatsapp" data-tab="sem-whatsapp">Sem WhatsApp</a>
        <a class="btn btn-primary btn-sm" href="/admin/dashboard.php?tab=pendencias" data-tab="pendencias">Pendência de cadastro</a>
        <a class="btn btn-primary btn-sm" href="/admin/dashboard.php?tab=mensalistas" data-tab="mensalistas">Mensalistas</a>
        <a class="btn btn-primary btn-sm" href="/admin/dashboard.php?tab=exclusoes" data-tab="exclusoes">Exclusões</a>
        <?php if ($canMergeDuplicates): ?>
          <a class="btn btn-primary btn-sm" href="/admin/dashboard.php?tab=duplicados" data-tab="duplicados">Duplicados</a>
        <?php endif; ?>
        <a class="btn btn-primary btn-sm" href="/admin/dashboard.php?tab=reset-senha" data-tab="reset-senha">Resetar senha</a>
        <a class="btn btn-primary btn-sm" href="/admin/dashboard.php?tab=fluxo-caixa" data-tab="fluxo-caixa">Fluxo de Caixa</a>
        <a class="btn btn-primary btn-sm" href="/admin/dashboard.php?tab=dados-asaas" data-tab="dados-asaas">Dados do Asaas</a>
        <a class="btn btn-primary btn-sm" href="/admin/dashboard.php?tab=entries" data-tab="entries">Entradas confirmadas</a>
      </div>

      <section id="tab-entries" class="<?php echo $activeTab === 'entries' ? '' : 'hidden'; ?>">
        <h2>Entradas confirmadas</h2>
        <p class="muted">Apenas conferência de day-use criado e pago corretamente.</p>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Aluno</th>
                <th>Matricula</th>
                <th>Pagamento</th>
                <th>Tipo</th>
                <th>Data do day-use</th>
                <th>Confirmado em</th>
                <th>Valor</th>
                <th>Codigo</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($payments) && empty($pendenciasPagas)): ?>
                <tr>
                  <td colspan="8">Nenhuma entrada confirmada para conferência.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                  <?php
                    $student = $payment['students'] ?? [];
                    $billing = in_array($payment['billing_type'], ['PIX', 'PIX_MANUAL'], true) ? 'PIX' : 'Debito';
                    $dailyRaw = $payment['daily_type'] ?? '';
                    $dailyBase = explode('|', $dailyRaw, 2)[0] ?? $dailyRaw;
                    $dailyLabel = $dailyBase === 'emergencial' ? 'Emergencial' : 'Planejada';
                    $amount = number_format((float) $payment['amount'], 2, ',', '.');
                    $dayUse = date('d/m/Y', strtotime($payment['payment_date']));
                    $confirmed = $payment['paid_at'] ? date('d/m/Y H:i', strtotime($payment['paid_at'])) : '-';
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($student['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($student['enrollment'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo $billing; ?></td>
                    <td><?php echo $dailyLabel; ?></td>
                    <td><?php echo $dayUse; ?></td>
                    <td><?php echo $confirmed; ?></td>
                    <td>R$ <?php echo $amount; ?></td>
                    <td><?php echo htmlspecialchars($payment['access_code'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php foreach ($pendenciasPagas as $p): ?>
                  <?php
                    $confirmed = $p['paid_at'] ? date('d/m/Y H:i', strtotime($p['paid_at'])) : '-';
                    $amount = number_format((float) $valorPendencia, 2, ',', '.');
                    $dayUse = !empty($p['payment_date']) ? date('d/m/Y', strtotime($p['payment_date'])) : '-';
                    $matricula = $p['enrollment'] ?? null;
                    $codigo = $p['access_code'] ?? null;
                    $cpfNaoVinculado = empty($matricula) && !empty($p['paid_at']);
                  ?>
                  <tr<?php echo $cpfNaoVinculado ? ' style="background:#FEF2F2;"' : ''; ?>>
                    <td><?php echo htmlspecialchars($p['student_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                      <?php echo htmlspecialchars($matricula ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                      <?php if ($cpfNaoVinculado): ?>
                        <span title="CPF não vinculado ao aluno matriculado" style="color:#B91C1C;font-size:11px;">⚠️</span>
                      <?php endif; ?>
                    </td>
                    <td>PIX</td>
                    <td>Pendência cadastro</td>
                    <td><?php echo $dayUse; ?></td>
                    <td><?php echo $confirmed; ?></td>
                    <td>R$ <?php echo $amount; ?></td>
                    <td><?php echo htmlspecialchars($codigo ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="tab-charges" class="<?php echo $activeTab === 'charges' ? '' : 'hidden'; ?>">
        <h2>Cobrança manual pós-chamada</h2>
        <p class="muted">Use quando o aluno frequentou sem pagamento antecipado. Registre a cobrança manual para revisão antes do envio.</p>

        <div class="form-group">
          <label>Aluno</label>
          <input id="charge-student" list="students-list" placeholder="Digite o nome do aluno" autocomplete="off" />
          <datalist id="students-list"></datalist>
        </div>

        <div id="charge-list" class="charge-list"></div>

        <button class="btn btn-primary" id="send-charges" type="button">Registrar cobranças manuais (sem envio)</button>
        <div id="charge-message" class="charge-message"></div>
      </section>

      <section id="tab-inadimplentes" class="<?php echo $activeTab === 'inadimplentes' ? '' : 'hidden'; ?>">
        <h2>Cobranças em aberto</h2>
        <p class="muted">Inclui cobranças da fila de envio e cobranças já enviadas que ainda não foram pagas.</p>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px;">
          <button id="send-selected-pending" class="btn btn-primary btn-sm" type="button">Enviar cobranças da fila</button>
          <label style="display:flex;gap:6px;align-items:center;font-size:13px;">
            <input id="select-all-pending" type="checkbox" />
            Selecionar todas da fila de envio
          </label>
        </div>
        <div id="send-pending-message" class="charge-message"></div>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Enviar</th>
                <th>Aluno</th>
                <th>Responsável</th>
                <th>E-mail</th>
                <th>Datas do day-use</th>
                <th>Valor</th>
                <th>Status</th>
                <th>Criado em</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($queuedPending) && empty($manualPending)): ?>
                <tr>
                  <td colspan="9">Nenhuma cobrança em aberto.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($queuedPending as $payment): ?>
                  <?php
                    $student = $payment['students'] ?? [];
                    $guardian = $payment['guardians'] ?? [];
                    $amount = number_format((float) $payment['amount'], 2, ',', '.');
                    $created = $payment['created_at'] ? date('d/m/Y H:i', strtotime($payment['created_at'])) : '-';
                    $dailyParts = explode('|', $payment['daily_type'] ?? '', 2);
                    $datesLabel = $dailyParts[1] ?? date('d/m/Y', strtotime($payment['payment_date']));
                  ?>
                  <?php
                    $paymentIdRow = trim((string) ($payment['id'] ?? ''));
                    $monthlyMeta = $paymentIdRow !== '' ? ($inadimplentesMonthlyMetaById[$paymentIdRow] ?? null) : null;
                    $isMonthlyCheck = is_array($monthlyMeta);
                    $monthlyDays = (int) ($monthlyMeta['weekly_days'] ?? 0);
                    $monthlyWarning = $isMonthlyCheck ? 'Aluno mensalista. Checar' : '';
                  ?>
                  <tr
                    class="inadimplente-row<?php echo $isMonthlyCheck ? ' monthly-check-row' : ''; ?>"
                    data-payment-id="<?php echo htmlspecialchars((string) ($payment['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    data-student="<?php echo htmlspecialchars((string) ($student['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    data-dayuse-date="<?php echo htmlspecialchars((string) $datesLabel, ENT_QUOTES, 'UTF-8'); ?>"
                    data-amount="<?php echo htmlspecialchars((string) ($payment['amount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>"
                    data-monthly="<?php echo $isMonthlyCheck ? '1' : '0'; ?>"
                    data-monthly-days="<?php echo $isMonthlyCheck ? htmlspecialchars((string) $monthlyDays, ENT_QUOTES, 'UTF-8') : ''; ?>"
                  >
                    <td>
                      <input class="pending-send-checkbox" type="checkbox" value="<?php echo htmlspecialchars($payment['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                    </td>
                    <td><?php echo htmlspecialchars($student['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['parent_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($datesLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>R$ <?php echo $amount; ?></td>
                    <td>
                      Na fila (não enviada)
                      <?php if ($isMonthlyCheck): ?>
                        <span class="monthly-check-badge"><?php echo htmlspecialchars($monthlyWarning . ' • ' . $monthlyDays . ' dias', ENT_QUOTES, 'UTF-8'); ?></span>
                      <?php endif; ?>
                    </td>
                    <td><?php echo $created; ?></td>
                    <td>
                      <button
                        class="btn btn-danger btn-sm js-delete-payment"
                        type="button"
                        data-id="<?php echo htmlspecialchars((string) ($payment['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                      >
                        Excluir
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php foreach ($manualPending as $payment): ?>
                  <?php
                    $student = $payment['students'] ?? [];
                    $guardian = $payment['guardians'] ?? [];
                    $amount = number_format((float) $payment['amount'], 2, ',', '.');
                    $created = $payment['created_at'] ? date('d/m/Y H:i', strtotime($payment['created_at'])) : '-';
                    $dailyParts = explode('|', $payment['daily_type'] ?? '', 2);
                    $datesLabel = $dailyParts[1] ?? date('d/m/Y', strtotime($payment['payment_date']));
                    $statusRaw = strtolower(trim((string) ($payment['status'] ?? 'pending')));
                    $statusMap = [
                        'pending' => 'Aguardando pagamento',
                        'overdue' => 'Vencida',
                        'awaiting_risk_analysis' => 'Em análise de risco',
                        'queued' => 'Na fila (não enviada)',
                    ];
                    $statusLabel = $statusMap[$statusRaw] ?? (trim((string) ($payment['status'] ?? '')) !== '' ? (string) $payment['status'] : 'Aguardando pagamento');
                  ?>
                  <?php
                    $paymentIdRow = trim((string) ($payment['id'] ?? ''));
                    $monthlyMeta = $paymentIdRow !== '' ? ($inadimplentesMonthlyMetaById[$paymentIdRow] ?? null) : null;
                    $isMonthlyCheck = is_array($monthlyMeta);
                    $monthlyDays = (int) ($monthlyMeta['weekly_days'] ?? 0);
                    $monthlyWarning = $isMonthlyCheck ? 'Aluno mensalista. Checar' : '';
                    if ($isMonthlyCheck) {
                        $statusLabel .= ' • ' . $monthlyWarning;
                    }
                  ?>
                  <tr
                    class="inadimplente-row<?php echo $isMonthlyCheck ? ' monthly-check-row' : ''; ?>"
                    data-payment-id="<?php echo htmlspecialchars((string) ($payment['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    data-student="<?php echo htmlspecialchars((string) ($student['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    data-dayuse-date="<?php echo htmlspecialchars((string) $datesLabel, ENT_QUOTES, 'UTF-8'); ?>"
                    data-amount="<?php echo htmlspecialchars((string) ($payment['amount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>"
                    data-monthly="<?php echo $isMonthlyCheck ? '1' : '0'; ?>"
                    data-monthly-days="<?php echo $isMonthlyCheck ? htmlspecialchars((string) $monthlyDays, ENT_QUOTES, 'UTF-8') : ''; ?>"
                  >
                    <td>-</td>
                    <td><?php echo htmlspecialchars($student['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['parent_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($datesLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>R$ <?php echo $amount; ?></td>
                    <td>
                      <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                      <?php if ($isMonthlyCheck): ?>
                        <span class="monthly-check-badge"><?php echo htmlspecialchars($monthlyDays . ' dias/semana', ENT_QUOTES, 'UTF-8'); ?></span>
                      <?php endif; ?>
                    </td>
                    <td><?php echo $created; ?></td>
                    <td>
                      <button
                        class="btn btn-danger btn-sm js-delete-payment"
                        type="button"
                        data-id="<?php echo htmlspecialchars((string) ($payment['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                      >
                        Excluir
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="tab-recebidas" class="<?php echo $activeTab === 'recebidas' ? '' : 'hidden'; ?>">
        <h2>Cobranças recebidas</h2>
        <p class="muted">Apenas conferência de cobranças pagas e regularizadas.</p>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px;">
          <button id="sync-recebidas-btn" class="btn btn-primary btn-sm" type="button">Atualizar conferência no Asaas</button>
          <div id="sync-recebidas-message" class="charge-message"></div>
        </div>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Aluno</th>
                <th>Responsável</th>
                <th>E-mail</th>
                <th>Datas do day-use</th>
                <th>Valor</th>
                <th>Recebido em</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($manualPaid) && empty($pendenciasPagas)): ?>
                <tr>
                  <td colspan="6">Nenhuma cobrança recebida para conferência.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($manualPaid as $payment): ?>
                  <?php
                    $student = $payment['students'] ?? [];
                    $guardian = $payment['guardians'] ?? [];
                    $amount = number_format((float) $payment['amount'], 2, ',', '.');
                    $paidAt = $payment['paid_at'] ? date('d/m/Y H:i', strtotime($payment['paid_at'])) : '-';
                    $dailyParts = explode('|', $payment['daily_type'] ?? '', 2);
                    $datesLabel = $dailyParts[1] ?? date('d/m/Y', strtotime($payment['payment_date']));
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($student['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['parent_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($datesLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>R$ <?php echo $amount; ?></td>
                    <td><?php echo $paidAt; ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php foreach ($pendenciasPagas as $p): ?>
                  <?php
                    $paidAt = $p['paid_at'] ? date('d/m/Y H:i', strtotime($p['paid_at'])) : '-';
                    $amount = number_format((float) $valorPendencia, 2, ',', '.');
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($p['student_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($p['guardian_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($p['guardian_email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>Pendência cadastro</td>
                    <td>R$ <?php echo $amount; ?></td>
                    <td><?php echo $paidAt; ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="tab-sem-whatsapp" class="<?php echo $activeTab === 'sem-whatsapp' ? '' : 'hidden'; ?>">
        <h2>Responsáveis sem WhatsApp</h2>
        <p class="muted">Aba de auditoria (somente conferência) para responsáveis sem celular cadastrado.</p>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Aluno</th>
                <th>Responsável</th>
                <th>E-mail</th>
                <th>CPF/CNPJ</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($missingWhatsapp)): ?>
                <tr>
                  <td colspan="4">Nenhum responsável sem WhatsApp no momento.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($missingWhatsapp as $guardian): ?>
                  <?php $student = $guardian['students'] ?? []; ?>
                  <tr>
                    <td><?php echo htmlspecialchars($student['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['parent_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['parent_document'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="tab-pendencias" class="<?php echo $activeTab === 'pendencias' ? '' : 'hidden'; ?>">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:8px;">
          <h2 style="margin:0;">Pendência de cadastro</h2>
          <a href="/admin/settle-pendencia.php" class="btn btn-danger btn-sm">Baixa manual (página dedicada)</a>
          <button id="sync-charges-payments-btn" class="btn btn-primary btn-sm" type="button">Atualizar cobranças e pagamentos</button>
        </div>
        <p class="muted">Esta aba deve receber apenas solicitações do botão Abrir Formulário no primeiro cadastro. Você pode mesclar com aluno existente ou incluir um novo aluno no banco.</p>
        <datalist id="pendencia-students-list"></datalist>
        <div id="sync-charges-payments-message" class="charge-message"></div>
        <div class="charge-fields" style="margin-bottom:12px;">
          <div class="form-group">
            <label>CPF para rechecagem</label>
            <input id="pendencia-cpf" type="text" placeholder="Digite o CPF" inputmode="numeric" />
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;">
            <button id="check-pendencia-cpf" class="btn btn-danger btn-sm" type="button">Checar por CPF</button>
          </div>
          <div class="form-group">
            <label>Cobrança Asaas</label>
            <input id="pendencia-asaas-id" type="text" placeholder="Ex: 742559970, pay_... ou link" />
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;">
            <button id="check-pendencia-asaas" class="btn btn-danger btn-sm" type="button">Checar por cobrança</button>
          </div>
        </div>
        <div id="pendencia-message" class="charge-message"></div>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Aluno</th>
                <th>Responsável</th>
                <th>CPF</th>
                <th>E-mail</th>
                <th>Data do day-use</th>
                <th>Registrado em</th>
                <th>Aluno no banco</th>
                <th>Ações do aluno</th>
                <th>Status Asaas</th>
                <th>Pago em</th>
                <th>Excluir pendência</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($pendencias)): ?>
                <tr>
                  <td colspan="11">Nenhuma pendência registrada.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($pendencias as $pendencia): ?>
                  <?php
                    $created = $pendencia['created_at'] ? date('d/m/Y H:i', strtotime($pendencia['created_at'])) : '-';
                    $paidAt = $pendencia['paid_at'] ? date('d/m/Y H:i', strtotime($pendencia['paid_at'])) : '-';
                    $dayUseDate = !empty($pendencia['payment_date']) ? date('d/m/Y', strtotime($pendencia['payment_date'])) : 'Não informado';
                    $linkedEnrollment = trim((string) ($pendencia['enrollment'] ?? ''));
                    $linkedStudentId = trim((string) ($pendencia['student_id'] ?? ''));
                    $linkedLabel = $linkedStudentId !== ''
                        ? 'Vinculado' . ($linkedEnrollment !== '' ? ' • Matrícula ' . $linkedEnrollment : '')
                        : 'Pendente de vínculo';
                  ?>
                  <tr
                    data-pendencia-id="<?php echo htmlspecialchars($pendencia['id'], ENT_QUOTES, 'UTF-8'); ?>"
                    data-student-id="<?php echo htmlspecialchars($linkedStudentId, ENT_QUOTES, 'UTF-8'); ?>"
                  >
                    <td data-col="student-name"><?php echo htmlspecialchars($pendencia['student_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($pendencia['guardian_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($pendencia['guardian_cpf'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($pendencia['guardian_email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($dayUseDate, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo $created; ?></td>
                    <td data-col="student-link">
                      <div class="pendencia-student-link">
                        <?php if ($linkedStudentId !== ''): ?>
                          <?php echo htmlspecialchars($linkedLabel, ENT_QUOTES, 'UTF-8'); ?>
                        <?php else: ?>
                          <span class="pending">Pendente de vínculo</span>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td>
                      <div class="pendencia-student-actions">
                        <input
                          type="text"
                          class="input-sm pendencia-student-lookup"
                          list="pendencia-students-list"
                          placeholder="Aluno existente no banco"
                        />
                        <button
                          class="btn btn-ghost btn-sm js-pendencia-link-student"
                          type="button"
                          data-id="<?php echo htmlspecialchars($pendencia['id'], ENT_QUOTES, 'UTF-8'); ?>"
                        >
                          Mesclar com existente
                        </button>
                        <button
                          class="btn btn-primary btn-sm js-pendencia-create-student"
                          type="button"
                          data-id="<?php echo htmlspecialchars($pendencia['id'], ENT_QUOTES, 'UTF-8'); ?>"
                        >
                          Incluir aluno no banco
                        </button>
                      </div>
                    </td>
                    <td data-col="asaas-status">-</td>
                    <td data-col="paid-at"><?php echo $paidAt; ?></td>
                    <td data-col="action">
                      <?php if (!empty($pendencia['paid_at'])): ?>
                        -
                      <?php else: ?>
                        <button
                          class="btn btn-danger btn-sm js-delete-pendencia"
                          type="button"
                          data-id="<?php echo htmlspecialchars($pendencia['id'], ENT_QUOTES, 'UTF-8'); ?>"
                        >
                          Excluir
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="tab-mensalistas" class="<?php echo $activeTab === 'mensalistas' ? '' : 'hidden'; ?>">
        <h2>Mensalistas</h2>
        <p class="muted">Marque no cadastro os alunos mensalistas com 2, 3, 4 ou 5 dias por semana.</p>
        <div class="charge-fields" style="margin-bottom:12px;">
          <div class="form-group">
            <label>Aluno</label>
            <input id="monthly-student" type="text" list="students-list" placeholder="Digite o nome do aluno" autocomplete="off" />
          </div>
          <div class="form-group">
            <label>Dias por semana</label>
            <div class="monthly-days-wrap">
              <label><input type="radio" name="monthly-days" value="5" /> 5 dias</label>
              <label><input type="radio" name="monthly-days" value="4" /> 4 dias</label>
              <label><input type="radio" name="monthly-days" value="3" /> 3 dias</label>
              <label><input type="radio" name="monthly-days" value="2" /> 2 dias</label>
            </div>
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;gap:8px;">
            <button id="monthly-save-btn" class="btn btn-primary btn-sm" type="button">Salvar mensalista</button>
            <button id="monthly-remove-btn" class="btn btn-danger btn-sm" type="button">Remover mensalista</button>
          </div>
        </div>
        <div id="monthly-message" class="charge-message"></div>
        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Aluno</th>
                <th>Matrícula</th>
                <th>Plano semanal</th>
                <th>Atualizado em</th>
                <th>Atualizado por</th>
              </tr>
            </thead>
            <tbody id="monthly-table-body">
              <?php if (empty($monthlyRowsForJs)): ?>
                <tr><td colspan="5">Nenhum mensalista cadastrado.</td></tr>
              <?php else: ?>
                <?php foreach ($monthlyRowsForJs as $monthlyRow): ?>
                  <tr data-student-id="<?php echo htmlspecialchars((string) ($monthlyRow['student_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <td><?php echo htmlspecialchars((string) ($monthlyRow['student_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($monthlyRow['enrollment'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($monthlyRow['weekly_days'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?> dias/semana</td>
                    <td><?php echo !empty($monthlyRow['updated_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $monthlyRow['updated_at'])), ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                    <td><?php echo htmlspecialchars((string) (($monthlyRow['updated_by'] ?? '') ?: '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="tab-exclusoes" class="<?php echo $activeTab === 'exclusoes' ? '' : 'hidden'; ?>">
        <h2>Histórico de exclusões</h2>
        <p class="muted">Registro de exclusões de cobranças e pendências com motivo informado.</p>
        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Data da exclusão</th>
                <th>Tipo</th>
                <th>Aluno</th>
                <th>Responsável</th>
                <th>Data do day-use</th>
                <th>Valor</th>
                <th>Motivo</th>
                <th>Origem</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($exclusionsLog)): ?>
                <tr>
                  <td colspan="8">Nenhuma exclusão registrada ainda.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($exclusionsLog as $entry): ?>
                  <?php
                    $deletedAt = !empty($entry['deleted_at']) ? date('d/m/Y H:i', strtotime((string) $entry['deleted_at'])) : '-';
                    $dayUseDate = !empty($entry['payment_date']) ? date('d/m/Y', strtotime((string) $entry['payment_date'])) : '-';
                    $amountNumber = (float) ($entry['amount'] ?? 0);
                    $amountLabel = $amountNumber > 0 ? ('R$ ' . number_format($amountNumber, 2, ',', '.')) : '-';
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($deletedAt, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($entry['entity_type'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($entry['student_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($entry['guardian_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($dayUseDate, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($amountLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($entry['reason'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($entry['source'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <?php if ($canMergeDuplicates): ?>
      <section id="tab-duplicados" class="<?php echo $activeTab === 'duplicados' ? '' : 'hidden'; ?>">
        <h2>Alunos duplicados</h2>
        <p class="muted">Mescla automática por nome ou matrícula (mantém o registro mais antigo). Abaixo listamos possíveis duplicados por CPF do responsável.</p>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Critério</th>
                <th>Aluno</th>
                <th>IDs</th>
                <th>Matrículas</th>
                <th>Ativo</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($duplicateGroups) && empty($duplicateEnrollmentGroups)): ?>
                <tr>
                  <td colspan="6">Nenhum duplicado encontrado.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($duplicateGroups as $group): ?>
                  <?php
                    $primary = $group[0];
                    $duplicateIds = array_map(static fn($s) => $s['id'], array_slice($group, 1));
                    $ids = array_map(static fn($s) => $s['id'], $group);
                    $enrollments = array_map(static fn($s) => $s['enrollment'] ?? '-', $group);
                    $actives = array_map(static fn($s) => ($s['active'] ? 'Sim' : 'Não'), $group);
                  ?>
                  <tr>
                    <td>Nome</td>
                    <td><?php echo htmlspecialchars($primary['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $ids), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $enrollments), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $actives), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                      <button class="btn btn-primary btn-sm js-merge-duplicates"
                        type="button"
                        data-primary="<?php echo htmlspecialchars($primary['id'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-duplicates="<?php echo htmlspecialchars(json_encode($duplicateIds), ENT_QUOTES, 'UTF-8'); ?>">
                        Mesclar duplicados
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php foreach ($duplicateEnrollmentGroups as $group): ?>
                  <?php
                    $primary = $group[0];
                    $duplicateIds = array_map(static fn($s) => $s['id'], array_slice($group, 1));
                    $ids = array_map(static fn($s) => $s['id'], $group);
                    $enrollments = array_map(static fn($s) => $s['enrollment'] ?? '-', $group);
                    $actives = array_map(static fn($s) => ($s['active'] ? 'Sim' : 'Não'), $group);
                  ?>
                  <tr>
                    <td>Matrícula</td>
                    <td><?php echo htmlspecialchars($primary['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $ids), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $enrollments), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $actives), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                      <button class="btn btn-primary btn-sm js-merge-duplicates"
                        type="button"
                        data-primary="<?php echo htmlspecialchars($primary['id'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-duplicates="<?php echo htmlspecialchars(json_encode($duplicateIds), ENT_QUOTES, 'UTF-8'); ?>">
                        Mesclar duplicados
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if (!empty($cpfDuplicateGroups)): ?>
          <div style="margin-top:18px;overflow-x:auto;">
            <table class="admin-table">
              <thead>
                <tr style="text-align:left;">
                  <th>Possível duplicado (CPF do responsável)</th>
                  <th>CPF</th>
                  <th>IDs</th>
                  <th>Matrículas</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($cpfDuplicateGroups as $group): ?>
                  <?php
                    $cpf = $group[0]['parent_document'] ?? '-';
                    $names = array_map(static fn($g) => ($g['students']['name'] ?? '-'), $group);
                    $ids = array_map(static fn($g) => ($g['students']['id'] ?? '-'), $group);
                    $enrollments = array_map(static fn($g) => ($g['students']['enrollment'] ?? '-'), $group);
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars(implode(' | ', $names), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($cpf, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $ids), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $enrollments), ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
        <div class="charge-message" id="merge-message"></div>
      </section>
      <?php endif; ?>

      <section id="tab-reset-senha" class="<?php echo $activeTab === 'reset-senha' ? '' : 'hidden'; ?>">
        <h2>Resetar senha do usuário</h2>
        <p class="muted">Busque o usuário pelo CPF e defina uma nova senha. Use para recuperação quando o responsável esquecer a senha.</p>

        <div class="charge-fields" style="margin-bottom:12px;">
          <div class="form-group">
            <label>CPF do responsável</label>
            <input id="reset-cpf" type="text" placeholder="Digite o CPF (apenas números)" inputmode="numeric" maxlength="14" />
          </div>
          <div class="form-group">
            <label>Nova senha</label>
            <input id="reset-senha-nova" type="password" placeholder="Mínimo 6 caracteres" minlength="6" autocomplete="new-password" />
          </div>
          <div class="form-group">
            <label>Confirmar nova senha</label>
            <input id="reset-senha-confirm" type="password" placeholder="Repita a nova senha" minlength="6" autocomplete="new-password" />
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;">
            <button id="reset-senha-btn" class="btn btn-danger btn-sm" type="button">Resetar senha</button>
          </div>
        </div>
        <div id="reset-senha-message" class="charge-message"></div>
      </section>

      <section id="tab-fluxo-caixa" class="<?php echo $activeTab === 'fluxo-caixa' ? '' : 'hidden'; ?>">
        <h2>Fluxo de Caixa</h2>
        <p class="muted">Visão operacional para conferência financeira com planilha offline.</p>

        <div class="cashflow-filters">
          <div class="form-group">
            <label>Data inicial</label>
            <input id="cashflow-from" type="date" />
          </div>
          <div class="form-group">
            <label>Data final</label>
            <input id="cashflow-to" type="date" />
          </div>
          <div class="form-group">
            <label>Aluno</label>
            <input id="cashflow-student" type="text" placeholder="Nome do aluno" />
          </div>
          <div class="form-group">
            <label>Matrícula</label>
            <input id="cashflow-enrollment" type="text" placeholder="Número de matrícula" />
          </div>
          <div class="form-group">
            <label>Tipo do day-use</label>
            <select id="cashflow-day-type">
              <option value="">Todos</option>
              <option value="planejada">Planejada</option>
              <option value="emergencial">Emergencial</option>
            </select>
          </div>
          <div class="form-group">
            <label>Status pagamento</label>
            <select id="cashflow-status">
              <option value="">Todos</option>
              <option value="paid">Pago</option>
              <option value="pending">Pendente</option>
              <option value="overdue">Vencido</option>
              <option value="canceled">Cancelado</option>
              <option value="refunded">Estornado</option>
            </select>
          </div>
          <div class="form-group">
            <label>Forma pagamento</label>
            <select id="cashflow-billing-type">
              <option value="">Todas</option>
              <option value="PIX">PIX</option>
              <option value="PIX_MANUAL">PIX manual</option>
              <option value="DEBIT_CARD">Cartão débito</option>
            </select>
          </div>
          <div class="form-group">
            <label>Mensalistas</label>
            <select id="cashflow-monthly-mode">
              <option value="subtract">Subtrair mensalistas (Aluno mensalista)</option>
              <option value="show">Mostrar mensalistas</option>
            </select>
          </div>
          <div class="form-group">
            <label>Não mostrar aluno</label>
            <input id="cashflow-exclude-student" type="text" placeholder="Ex.: Maria + João (ou vírgula)" />
          </div>
          <div class="form-group">
            <label>Não mostrar termo</label>
            <input id="cashflow-exclude-term" type="text" placeholder="Ex.: pendência + pix manual" />
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;gap:8px;">
            <button id="cashflow-search" class="btn btn-primary btn-sm" type="button">Buscar</button>
            <button id="cashflow-clear" class="btn btn-ghost btn-sm" type="button">Limpar</button>
          </div>
        </div>

        <div id="cashflow-message" class="charge-message"></div>
        <div id="cashflow-summary" class="cashflow-summary"></div>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Aluno</th>
                <th>Data</th>
                <th>Tipo day-use</th>
                <th>Matrícula</th>
                <th>Valor pago</th>
                <th>Status pagamento</th>
                <th>Forma pagamento</th>
              </tr>
            </thead>
            <tbody id="cashflow-tbody">
              <tr>
                <td colspan="7">Clique em "Buscar" para carregar.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section id="tab-dados-asaas" class="<?php echo $activeTab === 'dados-asaas' ? '' : 'hidden'; ?>">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <h2 style="margin:0;">Dados do Asaas</h2>
          <button id="asaas-data-refresh" class="btn btn-primary btn-sm" type="button">Atualizar direto do Asaas</button>
        </div>
        <p class="muted">Aba separada de conferência direta no Asaas (não altera dados locais).</p>
        <div id="asaas-data-message" class="charge-message"></div>
        <div id="asaas-data-summary" class="cashflow-summary"></div>

        <h3 style="margin-top:12px;">Pagos</h3>
        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>ID Asaas</th>
                <th>Status</th>
                <th>Cliente</th>
                <th>Descrição</th>
                <th>Vencimento</th>
                <th>Pago em</th>
                <th>Forma</th>
                <th>Valor</th>
                <th>Link</th>
              </tr>
            </thead>
            <tbody id="asaas-paid-tbody">
              <tr><td colspan="9">Clique em "Atualizar direto do Asaas".</td></tr>
            </tbody>
          </table>
        </div>

        <h3 style="margin-top:18px;">Pendentes</h3>
        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>ID Asaas</th>
                <th>Status</th>
                <th>Cliente</th>
                <th>Descrição</th>
                <th>Vencimento</th>
                <th>Pago em</th>
                <th>Forma</th>
                <th>Valor</th>
                <th>Link</th>
              </tr>
            </thead>
            <tbody id="asaas-pending-tbody">
              <tr><td colspan="9">Clique em "Atualizar direto do Asaas".</td></tr>
            </tbody>
          </table>
        </div>

        <h3 style="margin-top:18px;">Vencidos</h3>
        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>ID Asaas</th>
                <th>Status</th>
                <th>Cliente</th>
                <th>Descrição</th>
                <th>Vencimento</th>
                <th>Pago em</th>
                <th>Forma</th>
                <th>Valor</th>
                <th>Link</th>
              </tr>
            </thead>
            <tbody id="asaas-overdue-tbody">
              <tr><td colspan="9">Clique em "Atualizar direto do Asaas".</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <div class="footer">Desenvolvido por Lucas Gonçalves Junior - 2026</div>
  </div>

  <script>
    window.__adminDashboardBooted = false;
    window.__adminStudents = <?php echo json_encode($studentsForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    window.__monthlyStudents = <?php echo json_encode($monthlyRowsForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  </script>
  <script src="/assets/js/admin-dashboard.js?v=47"></script>
  <script>
    (function () {
      function activateTab(name) {
        var mapping = {
          entries: 'tab-entries',
          charges: 'tab-charges',
          inadimplentes: 'tab-inadimplentes',
          recebidas: 'tab-recebidas',
          'sem-whatsapp': 'tab-sem-whatsapp',
          pendencias: 'tab-pendencias',
          mensalistas: 'tab-mensalistas',
          exclusoes: 'tab-exclusoes',
          duplicados: 'tab-duplicados',
          'reset-senha': 'tab-reset-senha',
          'fluxo-caixa': 'tab-fluxo-caixa',
          'dados-asaas': 'tab-dados-asaas'
        };
        Object.keys(mapping).forEach(function (key) {
          var section = document.getElementById(mapping[key]);
          if (section) section.classList.toggle('hidden', key !== name);
        });
      }

      setTimeout(function () {
        if (window.__adminDashboardBooted) {
          return;
        }
        console.error('[admin-dashboard] JS externo não inicializou; fallback inline ativado.');
        var tabs = document.querySelectorAll('[data-tab]');
        tabs.forEach(function (btn) {
          btn.addEventListener('click', function () {
            var tab = btn.getAttribute('data-tab') || 'entries';
            activateTab(tab);
          });
        });
      }, 700);
    })();
  </script>
</body>
</html>
