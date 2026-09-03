<?php
declare(strict_types=1);

use App\Admin\Dashboard\Data\DashboardDefinition;
use App\ViteAssets;

$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/vendor/autoload.php';
require_once $projectRoot . '/src/Admin/Dashboard/Data/dashboard_view_functions.php';

$requestedRole = (string) ($_GET['role'] ?? '');
if (in_array($requestedRole, ['admin_principal', 'secretaria'], true)) {
    setcookie('e2e_admin_role', $requestedRole, [
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    $adminRole = $requestedRole;
} else {
    $adminRole = in_array(
        (string) ($_COOKIE['e2e_admin_role'] ?? ''),
        ['admin_principal', 'secretaria'],
        true
    ) ? (string) $_COOKIE['e2e_admin_role'] : 'admin_principal';
}

$isAdminPrincipal = $adminRole === 'admin_principal';
$allowedTabs = DashboardDefinition::tabsForRole($adminRole);
$activeTab = trim((string) ($_GET['tab'] ?? DashboardDefinition::defaultTab($adminRole)));
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = DashboardDefinition::defaultTab($adminRole);
}

$emptyDashboardData = [
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
    'duplicateGroups' => [],
    'duplicateEnrollmentGroups' => [],
    'cpfDuplicateGroups' => [],
    'guardiansById' => [],
    'familyLinkRequests' => [],
    'exclusionsLog' => [],
    'secretariaAccount' => null,
    'valorPendencia' => 77.00,
];

$dashboardContext = array_replace($emptyDashboardData, [
    'activeTab' => $activeTab,
    'adminCsrfToken' => 'e2e-csrf-token',
    'allowedTabs' => $allowedTabs,
    'assets' => ViteAssets::adminDashboard(),
    'canAttendanceApprove' => $isAdminPrincipal,
    'canViewAsUser' => $isAdminPrincipal,
    'dashboardTabs' => DashboardDefinition::tabs(),
    'isAdminPrincipal' => $isAdminPrincipal,
    'studentsForJs' => [
        [
            'id' => '11111111-1111-4111-8111-111111111111',
            'name' => 'Aluno Homônimo',
            'enrollment' => '10001',
            'grade' => 6,
            'class_name' => 'A',
        ],
        [
            'id' => '22222222-2222-4222-8222-222222222222',
            'name' => 'Aluno Homônimo',
            'enrollment' => '10002',
            'grade' => 7,
            'class_name' => 'B',
        ],
        [
            'id' => '33333333-3333-4333-8333-333333333333',
            'name' => '<img data-e2e src=x onerror="window.__e2eXss=true">',
            'enrollment' => '10003',
            'grade' => 8,
            'class_name' => 'C',
        ],
    ],
]);

define('ADMIN_DASHBOARD_COMPOSING', true);
require $projectRoot . '/src/Admin/Dashboard/View/layout.php';
