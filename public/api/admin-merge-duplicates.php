<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);

$primaryId = trim($payload['primary_id'] ?? '');
$duplicateIds = $payload['duplicate_ids'] ?? [];

if ($primaryId === '' || !is_array($duplicateIds) || !$duplicateIds) {
    Helpers::json(['ok' => false, 'error' => 'Dados inválidos.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$results = [
    'merged' => [],
    'errors' => [],
];

foreach ($duplicateIds as $dupId) {
    $dupId = trim((string) $dupId);
    if ($dupId === '' || $dupId === $primaryId) {
        continue;
    }

    $updateGuardians = $client->update('guardians', 'student_id=eq.' . urlencode($dupId), [
        'student_id' => $primaryId,
    ]);
    if (!$updateGuardians['ok']) {
        $results['errors'][] = ['id' => $dupId, 'error' => 'Falha ao atualizar responsáveis.'];
        continue;
    }

    $updatePayments = $client->update('payments', 'student_id=eq.' . urlencode($dupId), [
        'student_id' => $primaryId,
    ]);
    if (!$updatePayments['ok']) {
        $results['errors'][] = ['id' => $dupId, 'error' => 'Falha ao atualizar pagamentos.'];
        continue;
    }

    $updateStudent = $client->update('students', 'id=eq.' . urlencode($dupId), [
        'active' => false,
    ]);
    if (!$updateStudent['ok']) {
        $results['errors'][] = ['id' => $dupId, 'error' => 'Falha ao desativar aluno.'];
        continue;
    }

    $results['merged'][] = $dupId;
}

Helpers::json(['ok' => true, 'result' => $results]);
