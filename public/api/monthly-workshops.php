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

$user = Helpers::requireAuth();
$client = new SupabaseClient(new HttpClient());
$guardianId = trim((string) ($user['id'] ?? ''));
if ($guardianId === '') {
    Helpers::json(['ok' => false, 'error' => 'Responsável inválido.'], 401);
}

$guardianResult = $client->select(
    'guardians',
    'select=id,student_id&id=eq.' . rawurlencode($guardianId) . '&limit=1'
);
if (!($guardianResult['ok'] ?? false) || empty($guardianResult['data'][0])) {
    Helpers::json(['ok' => false, 'error' => 'Responsável não encontrado.'], 404);
}
$studentId = trim((string) ($guardianResult['data'][0]['student_id'] ?? ''));
if ($studentId === '') {
    Helpers::json(['ok' => false, 'error' => 'Aluno vinculado não encontrado.'], 422);
}

$service = new MonthlyWorkshopService($client);
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$payload = [];
if ($method === 'POST') {
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = [];
    }
}
$month = trim((string) (($method === 'GET' ? $_GET['month'] ?? null : $payload['month'] ?? null) ?: MonthlyWorkshopService::currentMonth()));

if ($method === 'GET') {
    $state = $service->getState($guardianId, $studentId, $month);
    Helpers::json($state, (int) ($state['status'] ?? 200));
}

Helpers::requirePost();
$action = strtolower(trim((string) ($payload['action'] ?? 'confirm')));
if ($action !== 'confirm') {
    Helpers::json(['ok' => false, 'error' => 'Ação inválida.'], 422);
}
$choices = $payload['choices'] ?? [];
if (!is_array($choices)) {
    $choices = [];
}

$result = $service->confirm($guardianId, $studentId, $month, $choices);
Helpers::json($result, (int) ($result['status'] ?? 200));
