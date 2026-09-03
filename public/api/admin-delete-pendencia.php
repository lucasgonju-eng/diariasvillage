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
use App\AsaasClient;
use App\HttpClient;
use App\Services\AsaasPaymentLifecycle;
use App\SupabaseClient;

function append_exclusion_log(array $entry): void
{
    \App\ExclusionLog::append($entry);
}

Helpers::requireAdminRole(\App\AdminAuth::ROLE_ADMIN);

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
$asaas = new AsaasClient(new HttpClient());
$paymentLifecycle = new AsaasPaymentLifecycle($asaas);
$rowResult = $client->select(
    'pendencia_de_cadastro',
    'select=id,student_name,guardian_name,guardian_email,guardian_cpf,payment_date,paid_at,status,'
        . 'asaas_payment_id,asaas_invoice_url'
        . '&id=eq.' . urlencode($pendenciaId) . '&limit=1'
);
$row = (($rowResult['ok'] ?? false) && !empty($rowResult['data'])) ? $rowResult['data'][0] : null;
if (!$row) {
    Helpers::json(['ok' => false, 'error' => 'Pendência não encontrada.'], 404);
}
if (!empty($row['paid_at']) || strtolower((string) ($row['status'] ?? '')) === 'paid') {
    Helpers::json(['ok' => false, 'error' => 'Não é possível cancelar uma pendência já paga.'], 422);
}
if (strtolower((string) ($row['status'] ?? '')) === 'canceled') {
    Helpers::json(['ok' => true, 'message' => 'Pendência já estava cancelada.']);
}

$asaasPaymentId = trim((string) ($row['asaas_payment_id'] ?? ''));
if ($asaasPaymentId === '') {
    Helpers::json([
        'ok' => false,
        'error' => 'Pendência sem ID Asaas. Concilie a identidade e a cobrança antes de cancelar.',
    ], 409);
}

$remoteCancel = $paymentLifecycle->cancelBeforeLocalMutation($asaasPaymentId, null, $row);
if (!($remoteCancel['ok'] ?? false)) {
    $httpStatus = ($remoteCancel['code'] ?? '') === 'ASAAS_LOOKUP_FAILED' ? 503 : 409;
    Helpers::json([
        'ok' => false,
        'error' => (string) ($remoteCancel['error'] ?? 'Cancelamento remoto bloqueado.'),
    ], $httpStatus);
}

$canceledAt = date('c');
$cancel = $client->update(
    'pendencia_de_cadastro',
    'id=eq.' . urlencode($pendenciaId) . '&status=eq.pending',
    [
        'status' => 'canceled',
        'canceled_at' => $canceledAt,
        'cancel_reason' => 'DIARIA_NAO_USADA',
    ]
);
if (!($cancel['ok'] ?? false) || empty($cancel['data'][0])) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao registrar cancelamento local.'], 500);
}

append_exclusion_log([
    'deleted_at' => $canceledAt,
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
    'message' => 'Pendência cancelada no Asaas e preservada no histórico local.',
    'pendencia' => [
        'id' => $pendenciaId,
        'student_name' => $row['student_name'] ?? '',
        'guardian_name' => $row['guardian_name'] ?? '',
        'payment_date' => $row['payment_date'] ?? '',
    ],
]);

