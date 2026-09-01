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

use App\AsaasClient;
use App\Helpers;
use App\HttpClient;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

Helpers::requirePost();

$expiresAt = strtotime('2026-09-01T16:15:00Z');
if ($expiresAt === false || time() > $expiresAt) {
    Helpers::json(['ok' => false, 'error' => 'Diagnóstico expirado.'], 410);
}

$authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
$token = str_starts_with($authorization, 'Bearer ')
    ? trim(substr($authorization, 7))
    : '';
$expectedHash = 'c71bfab9e09df064a0d05c07cb2a14d598a53c3f871e8cc26667cdf5475178e8';
if ($token === '' || !hash_equals($expectedHash, hash('sha256', $token))) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

$maskEmail = static function (string $email): string {
    $email = trim($email);
    if ($email === '' || !str_contains($email, '@')) {
        return '';
    }
    [$local, $domain] = explode('@', $email, 2);
    $visible = substr($local, 0, min(2, strlen($local)));
    return $visible . '***@' . $domain;
};

$maskDocument = static function (string $document): string {
    $digits = preg_replace('/\D+/', '', $document) ?? '';
    if (strlen($digits) < 5) {
        return $digits === '' ? '' : '***';
    }
    return substr($digits, 0, 3) . str_repeat('*', max(3, strlen($digits) - 5)) . substr($digits, -2);
};

$extractError = static function (array $response): array {
    $messages = [];
    $errors = $response['data']['errors'] ?? null;
    if (is_array($errors)) {
        foreach ($errors as $error) {
            if (!is_array($error)) {
                continue;
            }
            $message = trim((string) ($error['description'] ?? $error['message'] ?? ''));
            if ($message !== '') {
                $messages[] = $message;
            }
        }
    }
    $fallback = trim((string) ($response['error'] ?? ''));
    if (!$messages && $fallback !== '') {
        $messages[] = $fallback;
    }
    return [
        'http_status' => (int) ($response['status'] ?? 0),
        'messages' => $messages,
    ];
};

$asaas = new AsaasClient(new HttpClient());
$paymentIds = [
    'pay_fjc4l8b82jfjgcjl',
    'pay_bmkdzxssk23rqn0l',
    'pay_i04t8gbqxd7920hf',
    'pay_hduxx9gt4fajidxy',
];

$payments = [];
foreach ($paymentIds as $paymentId) {
    $response = $asaas->getPayment($paymentId);
    if (!($response['ok'] ?? false) || !is_array($response['data'] ?? null)) {
        $payments[] = [
            'id' => $paymentId,
            'found' => false,
            'error' => $extractError($response),
        ];
        continue;
    }

    $data = $response['data'];
    $payments[] = [
        'id' => (string) ($data['id'] ?? $paymentId),
        'found' => true,
        'status' => (string) ($data['status'] ?? ''),
        'value' => isset($data['value']) ? (float) $data['value'] : null,
        'net_value' => isset($data['netValue']) ? (float) $data['netValue'] : null,
        'billing_type' => (string) ($data['billingType'] ?? ''),
        'date_created' => (string) ($data['dateCreated'] ?? ''),
        'due_date' => (string) ($data['dueDate'] ?? ''),
        'payment_date' => (string) ($data['paymentDate'] ?? ''),
        'customer_id' => (string) ($data['customer'] ?? ''),
        'description' => (string) ($data['description'] ?? ''),
        'external_reference' => (string) ($data['externalReference'] ?? ''),
        'deleted' => (bool) ($data['deleted'] ?? false),
    ];
}

$customerId = 'cus_000160244939';
$customerResponse = $asaas->getCustomer($customerId);
$customer = [
    'id' => $customerId,
    'found' => false,
];
if (($customerResponse['ok'] ?? false) && is_array($customerResponse['data'] ?? null)) {
    $data = $customerResponse['data'];
    $customer = [
        'id' => (string) ($data['id'] ?? $customerId),
        'found' => true,
        'name' => (string) ($data['name'] ?? ''),
        'email_masked' => $maskEmail((string) ($data['email'] ?? '')),
        'document_masked' => $maskDocument((string) ($data['cpfCnpj'] ?? '')),
        'date_created' => (string) ($data['dateCreated'] ?? ''),
        'deleted' => (bool) ($data['deleted'] ?? false),
    ];
} else {
    $customer['error'] = $extractError($customerResponse);
}

Helpers::json([
    'ok' => true,
    'read_only' => true,
    'checked_at_utc' => gmdate('c'),
    'payments' => $payments,
    'customer' => $customer,
]);
