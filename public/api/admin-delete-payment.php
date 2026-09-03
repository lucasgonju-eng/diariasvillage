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

$paymentId = trim((string) ($payload['id'] ?? ''));
$reason = trim((string) ($payload['reason'] ?? ''));
$allowedReasons = ['COBRANCA_EM_DUPLICIDADE', 'MENSALISTA_COBERTO_PELO_PLANO'];

if ($paymentId === '') {
    Helpers::json(['ok' => false, 'error' => 'ID inválido.'], 422);
}
if (!in_array($reason, $allowedReasons, true)) {
    Helpers::json(['ok' => false, 'error' => 'Motivo inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$asaas = new AsaasClient(new HttpClient());
$paymentLifecycle = new AsaasPaymentLifecycle($asaas);
$paymentResult = $client->select(
    'payments',
    'select=id,student_id,guardian_id,status,paid_at,billing_type,asaas_payment_id,amount,payment_date,students(name),'
        . 'guardians(id,student_id,parent_name,email,parent_document,asaas_customer_id)'
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
    if ($reason === 'MENSALISTA_COBERTO_PELO_PLANO') {
        Helpers::json(['ok' => false, 'error' => 'Cobrança mensalista já encerrada; revise o histórico.'], 409);
    }
    Helpers::json(['ok' => true, 'message' => 'Cobrança já estava cancelada.']);
}
if ($status === 'processing_asaas') {
    Helpers::json([
        'ok' => false,
        'error' => 'Cobrança em conciliação financeira. Aguarde ou conclua a revisão antes de cancelar.',
    ], 409);
}

$knownRemoteResponse = null;
if ($reason === 'MENSALISTA_COBERTO_PELO_PLANO') {
    $studentId = trim((string) ($payment['student_id'] ?? ''));
    $paymentGuardianId = trim((string) ($payment['guardian_id'] ?? ''));
    $guardian = is_array($payment['guardians'] ?? null) ? $payment['guardians'] : [];
    $guardianId = trim((string) ($guardian['id'] ?? ''));
    $guardianStudentId = trim((string) ($guardian['student_id'] ?? ''));
    $paymentDate = trim((string) ($payment['payment_date'] ?? ''));
    if ($studentId === '' || $paymentDate === '' || $paymentDate >= '2026-09-01') {
        Helpers::json([
            'ok' => false,
            'error' => 'O cancelamento mensalista só é permitido para cobranças históricas anteriores a setembro.',
        ], 409);
    }
    if (
        $paymentGuardianId === ''
        || $guardianId === ''
        || !hash_equals($paymentGuardianId, $guardianId)
        || $guardianStudentId === ''
        || !hash_equals($studentId, $guardianStudentId)
    ) {
        Helpers::json([
            'ok' => false,
            'error' => 'Vínculo entre cobrança, responsável e aluno não confirmado.',
        ], 409);
    }
    $monthlyPlan = $client->select(
        'monthly_student_plans',
        'select=student_id&student_id=eq.' . rawurlencode($studentId) . '&active=eq.true&limit=1'
    );
    if (!($monthlyPlan['ok'] ?? false) || empty($monthlyPlan['data'][0])) {
        Helpers::json([
            'ok' => false,
            'error' => 'Plano mensalista ativo não confirmado; cancelamento bloqueado.',
        ], 409);
    }

    $monthlyAsaasPaymentId = trim((string) ($payment['asaas_payment_id'] ?? ''));
    if ($monthlyAsaasPaymentId === '') {
        Helpers::json([
            'ok' => false,
            'error' => 'Cobrança mensalista sem ID Asaas; conciliação remota obrigatória.',
        ], 409);
    }
    $knownRemoteResponse = $asaas->getPayment($monthlyAsaasPaymentId);
    if (!($knownRemoteResponse['ok'] ?? false)) {
        Helpers::json([
            'ok' => false,
            'error' => 'Cobrança mensalista não foi confirmada diretamente no Asaas.',
        ], 503);
    }
}

$asaasPaymentId = trim((string) ($payment['asaas_payment_id'] ?? ''));
$billingType = strtoupper(trim((string) ($payment['billing_type'] ?? '')));
if ($asaasPaymentId !== '') {
    $guardianIdentity = is_array($payment['guardians'] ?? null) ? $payment['guardians'] : [];
    $remoteCancel = $paymentLifecycle->cancelBeforeLocalMutation(
        $asaasPaymentId,
        $knownRemoteResponse,
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
    'reason' => $reason,
    'source' => 'admin_inadimplentes',
    'notes' => '',
]);

Helpers::json([
    'ok' => true,
    'message' => 'Cobrança cancelada no Asaas e preservada no histórico local.',
]);

