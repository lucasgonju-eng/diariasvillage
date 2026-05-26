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

function append_payment_settlement_log(array $entry): void
{
    $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'payment_settlements_log.jsonl';
    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($line) || $line === '') {
        return;
    }
    @file_put_contents($path, $line . PHP_EOL, FILE_APPEND);
}

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}
if (($_SESSION['admin_user'] ?? '') !== 'admin') {
    Helpers::json(['ok' => false, 'error' => 'Recurso disponível apenas para o admin principal.'], 403);
}

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = [];
}

$paymentId = trim((string) ($payload['payment_id'] ?? ($payload['id'] ?? '')));
$note = trim((string) ($payload['note'] ?? ''));

if ($paymentId === '') {
    Helpers::json(['ok' => false, 'error' => 'ID da cobrança inválido.'], 422);
}
if ($note === '') {
    Helpers::json(['ok' => false, 'error' => 'Informe a observação/motivo da baixa.'], 422);
}
$noteLength = function_exists('mb_strlen') ? mb_strlen($note, 'UTF-8') : strlen($note);
if ($noteLength > 500) {
    Helpers::json(['ok' => false, 'error' => 'Observação muito longa. Use até 500 caracteres.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$paymentResult = $client->select(
    'payments',
    'select=id,student_id,guardian_id,payment_date,daily_type,amount,status,billing_type,paid_at,asaas_payment_id,students(name,enrollment),guardians(parent_name,email)&id=eq.'
        . urlencode($paymentId)
        . '&limit=1'
);
$payment = (($paymentResult['ok'] ?? false) && !empty($paymentResult['data'][0])) ? $paymentResult['data'][0] : null;
if (!is_array($payment)) {
    Helpers::json(['ok' => false, 'error' => 'Cobrança não encontrada.'], 404);
}

$status = strtolower(trim((string) ($payment['status'] ?? '')));
$billingType = strtoupper(trim((string) ($payment['billing_type'] ?? '')));
if ($status === 'paid' || !empty($payment['paid_at'])) {
    Helpers::json(['ok' => false, 'error' => 'Esta cobrança já está baixada/paga.'], 422);
}
if ($billingType !== 'PIX_MANUAL') {
    Helpers::json(['ok' => false, 'error' => 'Baixa manual disponível apenas para cobranças PIX_MANUAL.'], 422);
}
if (!in_array($status, ['pending', 'pending_asaas', 'overdue', 'awaiting_risk_analysis'], true)) {
    Helpers::json(['ok' => false, 'error' => 'Status não permite baixa manual.'], 422);
}

$settledAt = date('c');
$update = $client->update('payments', 'id=eq.' . urlencode($paymentId), [
    'status' => 'paid',
    'paid_at' => $settledAt,
]);
if (!($update['ok'] ?? false) || empty($update['data'][0])) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao registrar baixa manual.'], 500);
}

$student = is_array($payment['students'] ?? null) ? $payment['students'] : [];
$guardian = is_array($payment['guardians'] ?? null) ? $payment['guardians'] : [];
append_payment_settlement_log([
    'settled_at' => $settledAt,
    'payment_id' => $paymentId,
    'student_id' => (string) ($payment['student_id'] ?? ''),
    'student_name' => (string) ($student['name'] ?? ''),
    'enrollment' => (string) ($student['enrollment'] ?? ''),
    'guardian_id' => (string) ($payment['guardian_id'] ?? ''),
    'guardian_name' => (string) ($guardian['parent_name'] ?? ''),
    'guardian_email' => (string) ($guardian['email'] ?? ''),
    'payment_date' => (string) ($payment['payment_date'] ?? ''),
    'daily_type' => (string) ($payment['daily_type'] ?? ''),
    'amount' => (float) ($payment['amount'] ?? 0),
    'previous_status' => (string) ($payment['status'] ?? ''),
    'billing_type' => (string) ($payment['billing_type'] ?? ''),
    'asaas_payment_id' => (string) ($payment['asaas_payment_id'] ?? ''),
    'note' => $note,
    'settled_by' => (string) ($_SESSION['admin_user'] ?? ''),
    'source' => 'admin_cashflow',
]);

Helpers::json([
    'ok' => true,
    'message' => 'Baixa manual registrada.',
    'paid_at' => $settledAt,
]);
