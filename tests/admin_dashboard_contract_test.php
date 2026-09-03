<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $relativePath) use ($root, &$failures): string {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $contents = @file_get_contents($path);
    if (!is_string($contents)) {
        $failures[] = 'Arquivo obrigatório ausente: ' . $relativePath;
        return '';
    }
    return $contents;
};

$contains = static function (string $label, string $source, string $needle) use (&$failures): void {
    if (!str_contains($source, $needle)) {
        $failures[] = $label . ' (ausente: ' . $needle . ')';
    }
};

$dashboard = $read('public/admin/dashboard.php');
$layout = $read('src/Admin/Dashboard/View/layout.php');
$definition = $read('src/Admin/Dashboard/Data/DashboardDefinition.php');
$viewSources = $layout;
foreach (glob($root . '/src/Admin/Dashboard/View/partials/*.php') ?: [] as $partialPath) {
    $viewSources .= "\n" . (file_get_contents($partialPath) ?: '');
}
$javascript = '';
foreach (glob($root . '/frontend/admin/{core,domains}/*.ts', GLOB_BRACE) ?: [] as $modulePath) {
    $javascript .= "\n" . (file_get_contents($modulePath) ?: '');
}
$attendance = $read('public/api/admin-attendance.php');

$contains('dashboard exige sessão administrativa', $dashboard, 'Helpers::requireAdminWeb()');
$contains('dashboard resolve raiz local e release achatada', $dashboard, "is_file(dirname(__DIR__) . '/src/Bootstrap.php')");
$contains('dashboard publica CSRF em meta protegida', $layout, 'meta name="admin-csrf-token"');
foreach (['JSON_HEX_TAG', 'JSON_HEX_AMP', 'JSON_HEX_APOS', 'JSON_HEX_QUOT'] as $flag) {
    $contains('bootstrap JSON usa ' . $flag, $layout, $flag);
}

$sharedTabs = ['chamada', 'familias', 'sem-whatsapp', 'mensalistas', 'entries'];
foreach ($sharedTabs as $tab) {
    $contains('aba compartilhada registrada: ' . $tab, $definition, "'" . $tab . "' =>");
    $contains('seção compartilhada existe: ' . $tab, $viewSources, 'id="tab-' . $tab . '"');
}

$adminTabs = [
    'charges',
    'inadimplentes',
    'recebidas',
    'pendencias',
    'oficinas-modulares',
    'exclusoes',
    'duplicados',
    'reset-senha',
    'acesso-secretaria',
    'fluxo-caixa',
    'dados-asaas',
    'email-massa',
];
foreach ($adminTabs as $tab) {
    $contains('aba administrativa registrada: ' . $tab, $definition, "'" . $tab . "' =>");
    $contains('seção administrativa existe: ' . $tab, $viewSources, 'id="tab-' . $tab . '"');
}
$contains('navegação usa matriz de abas permitidas', $layout, 'foreach ($allowedTabs as $tabName)');
$contains('navegação preserva data-tab', $layout, 'data-tab="<?php echo htmlspecialchars($tabName');

$domContracts = [
    'admin-view-user-guardian',
    'view-user-student-id',
    'charge-student',
    'charge-list',
    'attendance-day-list',
    'attendance-tbody',
    'attendance-students-list',
    'attendance-offices-list',
    'monthly-student',
    'monthly-students-list',
    'reset-guardian',
    'secretaria-password',
    'cashflow-tbody',
    'asaas-paid-tbody',
    'bulk-mail-visual',
];
foreach ($domContracts as $id) {
    $contains('contrato DOM preservado: ' . $id, $viewSources, 'id="' . $id . '"');
}

$contains('frontend envia UUID do aluno', $javascript, 'student_id: resolved.id');
$contains('frontend envia UUID do responsável', $javascript, 'guardian_id: selectedGuardianId');
$contains('frontend mantém rótulo com matrícula', $javascript, 'Matrícula ${enrollment}');
$contains('frontend injeta CSRF nas mutações same-origin', $javascript, "headers.set('X-CSRF-Token', token)");
$contains('frontend restringe aprovação da chamada por capability', $javascript, 'adminCanApproveAttendance');
foreach (['getStudentByName', 'resolveStudentNameForAdmin', 'monthlyByName'] as $ambiguousSelector) {
    if (str_contains($javascript, $ambiguousSelector)) {
        $failures[] = 'frontend mantém seleção ambígua por nome: ' . $ambiguousSelector;
    }
}
$contains(
    'autocomplete da chamada usa rótulo com matrícula',
    $javascript,
    'optionAttendance.value = runtime.formatStudentIdentityLabel(student)'
);

$contains('chamada aceita secretaria e admin', $attendance, 'AdminAuth::ROLE_SECRETARIA');
$contains('fechamento cria estado em revisão', $attendance, 'AttendanceCalls::STATUS_EM_REVISAO');
$contains('aprovação continua ação separada', $attendance, "['approve', 'audit']");
$contains('rejeição continua ação separada', $attendance, "\$action === 'reject'");
$contains('auditoria continua ação separada', $attendance, "\$action === 'audit'");
$contains('aprovação exige admin principal', $attendance, 'if (!isAdminUser())');
if (str_contains($attendance, '&name=eq.')) {
    $failures[] = 'chamada não pode localizar aluno por nome';
}
$closeDayStart = strpos($attendance, "if (\$action === 'close_day')");
$createStart = strpos($attendance, "if (\$action === 'create')");
if ($closeDayStart === false || $createStart === false || $createStart <= $closeDayStart) {
    $failures[] = 'não foi possível isolar o fechamento da chamada';
} else {
    $closeDayFlow = substr($attendance, $closeDayStart, $createStart - $closeDayStart);
    if (str_contains($closeDayFlow, 'createPayment(') || str_contains($closeDayFlow, "insert('payments'")) {
        $failures[] = 'fechar chamada não pode criar cobrança';
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Falhas no contrato do dashboard administrativo:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK: contratos de RBAC, DOM, UUID, CSRF e chamada administrativa preservados.\n";
