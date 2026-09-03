<?php
declare(strict_types=1);

$appRoot = is_file(dirname(__DIR__) . '/src/Bootstrap.php')
    ? dirname(__DIR__)
    : dirname(__DIR__, 2);
require_once $appRoot . '/src/Bootstrap.php';
require_once $appRoot . '/src/Admin/Dashboard/Data/dashboard_view_functions.php';

use App\Admin\Dashboard\Data\DashboardDataLoader;
use App\Admin\Dashboard\Data\DashboardDefinition;
use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;
use App\ViteAssets;

$adminSession = Helpers::requireAdminWeb();
$adminCsrfToken = trim((string) ($_SESSION['admin_csrf_token'] ?? ''));
if ($adminCsrfToken === '') {
    $adminCsrfToken = bin2hex(random_bytes(32));
    $_SESSION['admin_csrf_token'] = $adminCsrfToken;
}

$adminRole = (string) ($adminSession['role'] ?? '');
try {
    $allowedTabs = DashboardDefinition::tabsForRole($adminRole);
} catch (InvalidArgumentException $exception) {
    http_response_code(403);
    exit;
}

$isAdminPrincipal = $adminRole === 'admin_principal';
$defaultTab = DashboardDefinition::defaultTab($adminRole);
$activeTab = trim((string) ($_GET['tab'] ?? $defaultTab));
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = $defaultTab;
}

$client = new SupabaseClient(new HttpClient());
$dashboardData = DashboardDataLoader::create($client)
    ->loadForRole($adminRole);

$dashboardContext = array_replace($dashboardData, [
    'activeTab' => $activeTab,
    'adminCsrfToken' => $adminCsrfToken,
    'allowedTabs' => $allowedTabs,
    'assets' => ViteAssets::adminDashboard(),
    'canAttendanceApprove' => $isAdminPrincipal,
    'canViewAsUser' => $isAdminPrincipal,
    'dashboardTabs' => DashboardDefinition::tabs(),
    'isAdminPrincipal' => $isAdminPrincipal,
]);

define('ADMIN_DASHBOARD_COMPOSING', true);
require $appRoot . '/src/Admin/Dashboard/View/layout.php';
