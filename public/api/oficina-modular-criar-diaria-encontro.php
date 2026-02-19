<?php

require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\Services\OficinaModularGradeService;
use App\SupabaseClient;

Helpers::requirePost();
$user = Helpers::requireAuth();

$oficinaId = isset($_GET['oficinaId']) ? trim((string) $_GET['oficinaId']) : '';
if ($oficinaId === '') {
    Helpers::json(['ok' => false, 'error' => 'Oficina Modular não informada.'], 422);
}

$payload = json_decode(file_get_contents('php://input'), true);
$targetDia = isset($payload['target_dia_semana']) ? (int) $payload['target_dia_semana'] : null;
if ($targetDia !== null && ($targetDia < 1 || $targetDia > 7)) {
    Helpers::json(['ok' => false, 'error' => 'target_dia_semana inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$guardian = $client->select('guardians', 'select=id,student_id&id=eq.' . rawurlencode((string) $user['id']) . '&limit=1');
if (!$guardian['ok'] || empty($guardian['data'][0]['id'])) {
    Helpers::json(['ok' => false, 'error' => 'Responsável não encontrado.'], 404);
}
$guardianId = (string) $guardian['data'][0]['id'];
$studentId = (string) ($guardian['data'][0]['student_id'] ?? '');
if ($studentId === '') {
    Helpers::json(['ok' => false, 'error' => 'Responsável sem aluno vinculado.'], 422);
}

$service = new OficinaModularGradeService($client);
$result = $service->criarDiariaParaEncontro($oficinaId, $guardianId, $studentId, $targetDia);
if (($result['ok'] ?? false) !== true) {
    $status = (($result['reason'] ?? '') === 'TARGET_DIA_SEMANA_REQUIRED') ? 409 : 422;
    Helpers::json($result, $status);
}

Helpers::json($result);
