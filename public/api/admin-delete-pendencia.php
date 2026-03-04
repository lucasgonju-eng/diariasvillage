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
use App\SupabaseClient;

function append_exclusion_log(array $entry): void
{
    $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'exclusions_log.jsonl';
    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($line) || $line === '') {
        return;
    }
    @file_put_contents($path, $line . PHP_EOL, FILE_APPEND);
}

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = [];
}

$pendenciaId = trim((string) ($payload['id'] ?? ''));
$reason = trim((string) ($payload['reason'] ?? ''));

if ($pendenciaId === '') {
    Helpers::json(['ok' => false, 'error' => 'ID inválido.'], 422);
}
if ($reason !== 'DIARIA_NAO_USADA') {
    Helpers::json(['ok' => false, 'error' => 'Motivo inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$rowResult = $client->select(
    'pendencia_de_cadastro',
    'select=id,student_name,guardian_name,payment_date,paid_at&id=eq.' . urlencode($pendenciaId) . '&limit=1'
);
$row = (($rowResult['ok'] ?? false) && !empty($rowResult['data'])) ? $rowResult['data'][0] : null;
if (!$row) {
    Helpers::json(['ok' => false, 'error' => 'Pendência não encontrada.'], 404);
}
if (!empty($row['paid_at'])) {
    Helpers::json(['ok' => false, 'error' => 'Não é possível excluir uma pendência já paga.'], 422);
}

$delete = $client->delete('pendencia_de_cadastro', 'id=eq.' . urlencode($pendenciaId));
if (!($delete['ok'] ?? false)) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao excluir pendência.'], 500);
}

append_exclusion_log([
    'deleted_at' => date('c'),
    'entity_type' => 'pendencia',
    'entity_id' => $pendenciaId,
    'student_name' => trim((string) ($row['student_name'] ?? '')),
    'guardian_name' => trim((string) ($row['guardian_name'] ?? '')),
    'payment_date' => trim((string) ($row['payment_date'] ?? '')),
    'amount' => 77.0,
    'reason' => 'DIÁRIA NÃO USADA',
    'source' => 'admin_pendencias',
    'notes' => '',
]);

Helpers::json([
    'ok' => true,
    'message' => 'Pendência excluída com motivo: Diária não usada.',
    'reminder' => 'LEMBRETE: EXCLUA TAMBÉM A COBRANÇA NO ASAAS.',
    'pendencia' => [
        'id' => $pendenciaId,
        'student_name' => $row['student_name'] ?? '',
        'guardian_name' => $row['guardian_name'] ?? '',
        'payment_date' => $row['payment_date'] ?? '',
    ],
]);

