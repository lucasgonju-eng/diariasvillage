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

$paymentId = trim((string) ($payload['id'] ?? ''));
$reason = trim((string) ($payload['reason'] ?? ''));

if ($paymentId === '') {
    Helpers::json(['ok' => false, 'error' => 'ID inválido.'], 422);
}
if ($reason !== 'COBRANCA_EM_DUPLICIDADE') {
    Helpers::json(['ok' => false, 'error' => 'Motivo inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$paymentResult = $client->select(
    'payments',
    'select=id,status,amount,payment_date,students(name),guardians(parent_name,email)&id=eq.' . urlencode($paymentId) . '&limit=1'
);
$payment = (($paymentResult['ok'] ?? false) && !empty($paymentResult['data'])) ? $paymentResult['data'][0] : null;
if (!$payment) {
    Helpers::json(['ok' => false, 'error' => 'Cobrança não encontrada.'], 404);
}

$status = strtolower(trim((string) ($payment['status'] ?? '')));
if ($status === 'paid') {
    Helpers::json(['ok' => false, 'error' => 'Não é possível excluir cobrança já paga.'], 422);
}

$delete = $client->delete('payments', 'id=eq.' . urlencode($paymentId) . '&status=in.(pending,queued)');
if (!($delete['ok'] ?? false)) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao excluir cobrança.'], 500);
}

$studentName = trim((string) ($payment['students']['name'] ?? ''));
$guardianName = trim((string) ($payment['guardians']['parent_name'] ?? ''));
$paymentDate = trim((string) ($payment['payment_date'] ?? ''));
$amount = (float) ($payment['amount'] ?? 0);

append_exclusion_log([
    'deleted_at' => date('c'),
    'entity_type' => 'payment',
    'entity_id' => $paymentId,
    'student_name' => $studentName,
    'guardian_name' => $guardianName,
    'payment_date' => $paymentDate,
    'amount' => $amount,
    'reason' => 'COBRANÇA EM DUPLICIDADE',
    'source' => 'admin_inadimplentes',
    'notes' => '',
]);

Helpers::json([
    'ok' => true,
    'message' => 'Cobrança excluída com motivo: Cobrança em duplicidade.',
]);

