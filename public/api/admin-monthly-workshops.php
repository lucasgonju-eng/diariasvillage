<?php

$bootstrapCandidates = [
    __DIR__ . '/../src/Bootstrap.php',
    dirname(__DIR__, 2) . '/src/Bootstrap.php',
];
foreach ($bootstrapCandidates as $bootstrapFile) {
    if (is_file($bootstrapFile)) {
        require_once $bootstrapFile;
        break;
    }
}

use App\Helpers;
use App\HttpClient;
use App\Services\MonthlyWorkshopService;
use App\SupabaseClient;

$admin = Helpers::requireAdminRole(['admin_principal', 'secretaria']);
$client = new SupabaseClient(new HttpClient());
$service = new MonthlyWorkshopService($client);
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $month = trim((string) ($_GET['month'] ?? MonthlyWorkshopService::currentMonth()));
    if (!preg_match('/^\d{4}-\d{2}-01$/', $month)) {
        Helpers::json(['ok' => false, 'error' => 'Competência mensal inválida.'], 422);
    }
    $result = $client->select(
        'monthly_workshop_submissions',
        'select=id,student_id,reference_month,weekly_days_snapshot,required_slots,status,confirmed_at,unlocked_at,'
            . 'students(name,enrollment)'
            . '&reference_month=eq.' . rawurlencode($month)
            . '&order=confirmed_at.desc'
            . '&limit=1000'
    );
    if (!($result['ok'] ?? false)) {
        Helpers::json(['ok' => false, 'error' => 'Não foi possível carregar confirmações mensalistas.'], 503);
    }
    Helpers::json(['ok' => true, 'items' => $result['data'] ?? []]);
}

Helpers::requirePost();
$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = [];
}
$action = strtolower(trim((string) ($payload['action'] ?? '')));
if ($action !== 'unlock') {
    Helpers::json(['ok' => false, 'error' => 'Ação inválida.'], 422);
}

$submissionId = trim((string) ($payload['submission_id'] ?? ''));
$adminId = trim((string) ($admin['id'] ?? ''));
$result = $service->unlock($submissionId, $adminId);
Helpers::json($result, (int) ($result['status'] ?? 200));
