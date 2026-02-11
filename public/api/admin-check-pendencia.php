<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\AsaasClient;
use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);
$pendenciaId = trim($payload['id'] ?? '');

if ($pendenciaId === '') {
    Helpers::json(['ok' => false, 'error' => 'ID inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$pendenciaResult = $client->select(
    'pendencia_de_cadastro',
    'select=id,paid_at,asaas_payment_id,asaas_invoice_url&' . 'id=eq.' . urlencode($pendenciaId)
);
if (!$pendenciaResult['ok'] || empty($pendenciaResult['data'])) {
    Helpers::json(['ok' => false, 'error' => 'Pendência não encontrada.'], 404);
}

$pendencia = $pendenciaResult['data'][0];
if (!empty($pendencia['paid_at'])) {
    Helpers::json(['ok' => true, 'paid_at' => $pendencia['paid_at'], 'status' => 'PAID']);
}

$asaas = new AsaasClient(new HttpClient());
$paymentId = trim((string) ($pendencia['asaas_payment_id'] ?? ''));
$invoiceUrl = trim((string) ($pendencia['asaas_invoice_url'] ?? ''));
$paymentData = null;

if ($paymentId !== '') {
    $paymentResponse = $asaas->getPayment($paymentId);
    $paymentData = $paymentResponse['ok'] ? ($paymentResponse['data'] ?? null) : null;
}

if (!$paymentData && $invoiceUrl !== '') {
    $paymentResponse = $asaas->findPaymentByInvoiceUrl($invoiceUrl);
    $list = $paymentResponse['ok'] ? ($paymentResponse['data']['data'] ?? []) : [];
    $paymentData = $list[0] ?? null;
}

if (!$paymentData) {
    Helpers::json(['ok' => false, 'error' => 'Pagamento não encontrado no Asaas.'], 404);
}

$status = $paymentData['status'] ?? '';
$paidStatuses = ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'];
if (in_array($status, $paidStatuses, true)) {
    $paidAt = date('c');
    $update = $client->update('pendencia_de_cadastro', 'id=eq.' . urlencode($pendenciaId), [
        'paid_at' => $paidAt,
        'asaas_payment_id' => $paymentData['id'] ?? $paymentId ?: null,
        'asaas_invoice_url' => $paymentData['invoiceUrl'] ?? $invoiceUrl ?: null,
    ]);
    if (!$update['ok']) {
        Helpers::json(['ok' => false, 'error' => 'Falha ao atualizar pendência.'], 500);
    }
    Helpers::json(['ok' => true, 'paid_at' => $paidAt, 'status' => $status]);
}

Helpers::json(['ok' => true, 'paid_at' => null, 'status' => $status]);
