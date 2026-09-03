<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Admin\Dashboard\Data\DashboardDefinition;

define('ADMIN_DASHBOARD_COMPOSING', true);

$baseData = [
    'payments' => [],
    'monthlyEntries' => [],
    'queuedPending' => [],
    'manualPending' => [],
    'monthlyRowsForJs' => [],
    'monthlySubmissions' => [],
    'inadimplentesMonthlyMetaById' => [],
    'manualPaid' => [],
    'missingWhatsapp' => [],
    'pendencias' => [],
    'pendenciasPagas' => [],
    'studentsById' => [],
    'studentsByEnrollment' => [],
    'studentsForJs' => [],
    'duplicateGroups' => [],
    'duplicateEnrollmentGroups' => [],
    'cpfDuplicateGroups' => [],
    'guardiansById' => [],
    'familyLinkRequests' => [],
    'exclusionsLog' => [],
    'secretariaAccount' => null,
    'valorPendencia' => 77.00,
];

$render = static function (string $role) use ($baseData): string {
    $isPrincipal = $role === 'admin_principal';
    $dashboardContext = array_replace($baseData, [
        'activeTab' => DashboardDefinition::defaultTab($role),
        'adminCsrfToken' => 'csrf-test',
        'allowedTabs' => DashboardDefinition::tabsForRole($role),
        'assets' => [
            'script' => '/assets/admin-dist/assets/admin-test.js',
            'styles' => ['/assets/admin-dist/assets/admin-test.css'],
        ],
        'canAttendanceApprove' => $isPrincipal,
        'canViewAsUser' => $isPrincipal,
        'dashboardTabs' => DashboardDefinition::tabs(),
        'isAdminPrincipal' => $isPrincipal,
    ]);

    ob_start();
    require dirname(__DIR__) . '/src/Admin/Dashboard/View/layout.php';
    return (string) ob_get_clean();
};

$failures = [];
$secretariaHtml = $render('secretaria');
$principalHtml = $render('admin_principal');

foreach (['chamada', 'familias', 'sem-whatsapp', 'mensalistas', 'entries'] as $tab) {
    if (!str_contains($secretariaHtml, 'id="tab-' . $tab . '"')) {
        $failures[] = 'Secretaria não recebeu aba operacional: ' . $tab;
    }
}

foreach ([
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
] as $tab) {
    if (str_contains($secretariaHtml, 'id="tab-' . $tab . '"')) {
        $failures[] = 'Secretaria recebeu shell restrito: ' . $tab;
    }
    if (!str_contains($principalHtml, 'id="tab-' . $tab . '"')) {
        $failures[] = 'Admin principal não recebeu aba autorizada: ' . $tab;
    }
}

if (str_contains($secretariaHtml, 'window.__adminCanApproveAttendance = true')) {
    $failures[] = 'Secretaria recebeu capability cliente de aprovação da chamada.';
}
if (str_contains($secretariaHtml, 'attendance-go-inadimplentes-btn')) {
    $failures[] = 'Secretaria recebeu controle de liberação da fila financeira.';
}
if (!str_contains($principalHtml, 'window.__adminCanApproveAttendance = true')) {
    $failures[] = 'Admin principal não recebeu capability de aprovação da chamada.';
}
if (!str_contains($principalHtml, 'attendance-go-inadimplentes-btn')) {
    $failures[] = 'Admin principal não recebeu controle de liberação da fila.';
}
if (!str_contains($secretariaHtml, 'type="module"')) {
    $failures[] = 'Layout da secretaria não carregou o bundle Vite como módulo.';
}

if ($failures !== []) {
    fwrite(STDERR, "Falhas de RBAC nas views administrativas:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK: views administrativas renderizam somente as abas permitidas por papel.\n";
