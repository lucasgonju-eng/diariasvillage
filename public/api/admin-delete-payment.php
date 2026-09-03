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
    $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'exclusions_log.jsonl';
    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($line) || $line === '') {
        return;
    }
    @file_put_contents($path, $line . PHP_EOL, FILE_APPEND);
}

Helpers::requireAdminRole(\App\AdminAuth::ROLE_ADMIN);

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
$asaas = new AsaasClient(new HttpClient());
$paymentLifecycle = new AsaasPaymentLifecycle($asaas);
$paymentResult = $client->select(
    'payments',
    'select=id,status,paid_at,billing_type,asaas_payment_id,amount,payment_date,students(name),'
        . 'guardians(parent_name,email,parent_document,asaas_customer_id)'
        . '&id=eq.' . urlencode($paymentId) . '&limit=1'
);
$payment = (($paymentResult['ok'] ?? false) && !empty($paymentResult['data'])) ? $paymentResult['data'][0] : null;
if (!$payment) {
    Helpers::json(['ok' => false, 'error' => 'Cobrança não encontrada.'], 404);
}

$status = strtolower(trim((string) ($payment['status'] ?? '')));
if ($status === 'paid' || !empty($payment['paid_at'])) {
    Helpers::json(['ok' => false, 'error' => 'Não é possível cancelar cobrança já paga.'], 422);
}
if (in_array($status, ['canceled', 'cancelled', 'deleted', 'refunded'], true)) {
    Helpers::json(['ok' => true, 'message' => 'Cobrança já estava cancelada.']);
}
if ($status === 'processing_asaas') {
    Helpers::json([
        'ok' => false,
        'error' => 'Cobrança em conciliação financeira. Aguarde ou conclua a revisão antes de cancelar.',
    ], 409);
}

$asaasPaymentId = trim((string) ($payment['asaas_payment_id'] ?? ''));
$billingType = strtoupper(trim((string) ($payment['billing_type'] ?? '')));
if ($asaasPaymentId !== '') {
    $guardianIdentity = is_array($payment['guardians'] ?? null) ? $payment['guardians'] : [];
    $remoteCancel = $paymentLifecycle->cancelBeforeLocalMutation(
        $asaasPaymentId,
        null,
        $guardianIdentity
    );
    if (!($remoteCancel['ok'] ?? false)) {
        $httpStatus = ($remoteCancel['code'] ?? '') === 'ASAAS_LOOKUP_FAILED' ? 503 : 409;
        Helpers::json([
            'ok' => false,
            'error' => (string) ($remoteCancel['error'] ?? 'Cancelamento remoto bloqueado.'),
        ], $httpStatus);
    }
} elseif ($status !== 'queued' || $billingType !== 'PIX_MANUAL_QUEUE') {
    Helpers::json([
        'ok' => false,
        'error' => 'Cobrança sem vínculo Asaas em estado inconsistente. Revise antes de cancelar.',
    ], 409);
}

$cancel = $client->update(
    'payments',
    'id=eq.' . urlencode($paymentId)
        . '&status=in.(queued,pending,pending_asaas,overdue,awaiting_risk_analysis)',
    ['status' => 'canceled']
);
if (!($cancel['ok'] ?? false) || empty($cancel['data'][0])) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao registrar cancelamento local.'], 500);
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
    'message' => 'Cobrança cancelada no Asaas e preservada no histórico local.',
]);

