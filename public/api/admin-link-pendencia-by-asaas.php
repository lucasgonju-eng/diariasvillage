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
$pendenciaId = trim($payload['pendencia_id'] ?? '');
$asaasId = trim((string) ($payload['asaas_id'] ?? ''));

if ($pendenciaId === '' || $asaasId === '') {
    Helpers::json(['ok' => false, 'error' => 'Dados inválidos.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$pendenciaResult = $client->select(
    'pendencia_de_cadastro',
    'select=id,paid_at&' . 'id=eq.' . urlencode($pendenciaId)
);
if (!$pendenciaResult['ok'] || empty($pendenciaResult['data'])) {
    Helpers::json(['ok' => false, 'error' => 'Pendência não encontrada.'], 404);
}

$asaas = new AsaasClient(new HttpClient());
$paymentData = null;
$paymentResponse = $asaas->getPayment($asaasId);
if ($paymentResponse['ok']) {
    $paymentData = $paymentResponse['data'] ?? null;
}
if (!$paymentData) {
    $listResponse = $asaas->findPaymentByInvoiceNumber($asaasId);
    $list = $listResponse['ok'] ? ($listResponse['data']['data'] ?? []) : [];
    $paymentData = $list[0] ?? null;
}
if (!$paymentData) {
    $listResponse = $asaas->findPaymentByExternalReference($asaasId);
    $list = $listResponse['ok'] ? ($listResponse['data']['data'] ?? []) : [];
    $paymentData = $list[0] ?? null;
}
if (!$paymentData || empty($paymentData['id'])) {
    Helpers::json(['ok' => false, 'error' => 'Cobrança não encontrada no Asaas.'], 404);
}

$invoiceUrl = $paymentData['invoiceUrl'] ?? $paymentData['bankSlipUrl'] ?? '';
$status = $paymentData['status'] ?? '';
$paidStatuses = ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH', 'PAID'];
$payloadUpdate = [
    'asaas_payment_id' => $paymentData['id'],
    'asaas_invoice_url' => $invoiceUrl ?: null,
];

$paidAt = null;
if (in_array($status, $paidStatuses, true)) {
    $paidAt = date('c');
    $payloadUpdate['paid_at'] = $paidAt;
}

$update = $client->update('pendencia_de_cadastro', 'id=eq.' . urlencode($pendenciaId), $payloadUpdate);
if (!$update['ok']) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao atualizar pendência.'], 500);
}

Helpers::json([
    'ok' => true,
    'paid_at' => $paidAt,
    'status' => $status,
    'pendencia_id' => $pendenciaId,
]);
