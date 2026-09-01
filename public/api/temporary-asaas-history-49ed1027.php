<?php

require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\AsaasClient;
use App\Helpers;
use App\HttpClient;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

Helpers::requirePost();

if (time() > strtotime('2026-09-01T18:15:00Z')) {
    Helpers::json(['ok' => false, 'error' => 'Diagnóstico expirado.'], 410);
}

$authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
$token = str_starts_with($authorization, 'Bearer ')
    ? trim(substr($authorization, 7))
    : '';
if (
    $token === ''
    || !hash_equals(
        '49ed1027a0c1cae4a89f425acde9227862529adefb59bc203d19f083de877766',
        hash('sha256', $token)
    )
) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

$maskEmail = static function (string $email): string {
    $parts = explode('@', trim($email), 2);
    if (count($parts) !== 2) {
        return '';
    }
    return substr($parts[0], 0, 2) . '***@' . $parts[1];
};
$maskDocument = static function (string $document): string {
    $digits = preg_replace('/\D+/', '', $document) ?? '';
    if (strlen($digits) < 5) {
        return $digits === '' ? '' : '***';
    }
    return substr($digits, 0, 3) . str_repeat('*', max(3, strlen($digits) - 5)) . substr($digits, -2);
};
$sanitizePayment = static function (array $data): array {
    return [
        'id' => (string) ($data['id'] ?? ''),
        'status' => (string) ($data['status'] ?? ''),
        'value' => isset($data['value']) ? (float) $data['value'] : null,
        'date_created' => (string) ($data['dateCreated'] ?? ''),
        'due_date' => (string) ($data['dueDate'] ?? ''),
        'payment_date' => (string) ($data['paymentDate'] ?? ''),
        'customer_id' => (string) ($data['customer'] ?? ''),
        'description' => (string) ($data['description'] ?? ''),
        'external_reference' => (string) ($data['externalReference'] ?? ''),
        'deleted' => (bool) ($data['deleted'] ?? false),
    ];
};

$asaas = new AsaasClient(new HttpClient());
$contaminatedCustomerId = 'cus_000160244939';
$customerPayments = [];
$offset = 0;
do {
    $response = $asaas->listPaymentsByCustomer($contaminatedCustomerId, 100, $offset);
    if (!($response['ok'] ?? false) || !is_array($response['data'] ?? null)) {
        Helpers::json([
            'ok' => false,
            'error' => 'Falha ao listar cobranças do cliente contaminado.',
            'http_status' => (int) ($response['status'] ?? 0),
        ], 502);
    }
    $rows = is_array($response['data']['data'] ?? null) ? $response['data']['data'] : [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $customerPayments[] = $sanitizePayment($row);
        }
    }
    $hasMore = (bool) ($response['data']['hasMore'] ?? false);
    $offset += count($rows);
} while ($hasMore && $offset < 500);

$duplicateIds = [
    'pay_3y1klqdhp9znvjqg',
    'pay_eqnk06u74vkb16jd',
];
$duplicates = [];
$relatedCustomerIds = [];
foreach ($duplicateIds as $paymentId) {
    $response = $asaas->getPayment($paymentId);
    if (!($response['ok'] ?? false) || !is_array($response['data'] ?? null)) {
        $duplicates[] = [
            'id' => $paymentId,
            'found' => false,
            'http_status' => (int) ($response['status'] ?? 0),
        ];
        continue;
    }
    $payment = $sanitizePayment($response['data']);
    $payment['found'] = true;
    $duplicates[] = $payment;
    if ($payment['customer_id'] !== '') {
        $relatedCustomerIds[$payment['customer_id']] = true;
    }
}

$customers = [];
foreach (array_keys($relatedCustomerIds + [$contaminatedCustomerId => true]) as $customerId) {
    $response = $asaas->getCustomer($customerId);
    if (!($response['ok'] ?? false) || !is_array($response['data'] ?? null)) {
        $customers[] = [
            'id' => $customerId,
            'found' => false,
            'http_status' => (int) ($response['status'] ?? 0),
        ];
        continue;
    }
    $data = $response['data'];
    $customers[] = [
        'id' => (string) ($data['id'] ?? $customerId),
        'found' => true,
        'name' => (string) ($data['name'] ?? ''),
        'email_masked' => $maskEmail((string) ($data['email'] ?? '')),
        'document_masked' => $maskDocument((string) ($data['cpfCnpj'] ?? '')),
        'deleted' => (bool) ($data['deleted'] ?? false),
    ];
}

Helpers::json([
    'ok' => true,
    'read_only' => true,
    'checked_at_utc' => gmdate('c'),
    'contaminated_customer_id' => $contaminatedCustomerId,
    'contaminated_customer_payments' => $customerPayments,
    'duplicate_payments' => $duplicates,
    'customers' => $customers,
]);
