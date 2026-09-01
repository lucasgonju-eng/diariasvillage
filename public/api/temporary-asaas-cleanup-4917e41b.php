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

if (time() > strtotime('2026-09-01T19:15:00Z')) {
    Helpers::json(['ok' => false, 'error' => 'Operação expirada.'], 410);
}

$authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
$token = str_starts_with($authorization, 'Bearer ')
    ? trim(substr($authorization, 7))
    : '';
if (
    $token === ''
    || !hash_equals(
        '4917e41b3438bb3134591345ae2ab6a7f27ba137ec5fdaae39584e70bc0a3a62',
        hash('sha256', $token)
    )
) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

$contaminatedCustomerId = 'cus_000160244939';
$duplicateCustomerId = 'cus_000165132734';
$targets = [
    [
        'id' => 'pay_sigif2uknlys9hk1',
        'customer' => $contaminatedCustomerId,
        'value' => 97.00,
        'group' => 'contaminated',
    ],
    [
        'id' => 'pay_fjc4l8b82jfjgcjl',
        'customer' => $contaminatedCustomerId,
        'value' => 77.00,
        'group' => 'contaminated',
    ],
    [
        'id' => 'pay_bmkdzxssk23rqn0l',
        'customer' => $contaminatedCustomerId,
        'value' => 77.00,
        'group' => 'contaminated',
    ],
    [
        'id' => 'pay_eqnk06u74vkb16jd',
        'customer' => $duplicateCustomerId,
        'value' => 77.00,
        'group' => 'duplicate_newer',
        'external_reference' => '82aa829d-049b-4a77-b955-54ff3ec5d057',
    ],
];

$asaas = new AsaasClient(new HttpClient());
$customerResponse = $asaas->getCustomer($contaminatedCustomerId);
$customerData = is_array($customerResponse['data'] ?? null) ? $customerResponse['data'] : [];
$customerDocument = preg_replace('/\D+/', '', (string) ($customerData['cpfCnpj'] ?? '')) ?? '';
$customerMatched = ($customerResponse['ok'] ?? false)
    && (string) ($customerData['id'] ?? '') === $contaminatedCustomerId
    && (string) ($customerData['name'] ?? '') === 'Fernando de Brito Clemente'
    && $customerDocument === '00624611175'
    && !((bool) ($customerData['deleted'] ?? false));
if (!$customerMatched) {
    Helpers::json([
        'ok' => false,
        'error' => 'Cliente contaminado não corresponde ao estado autorizado.',
        'production_mutations' => 0,
    ], 409);
}

$preflight = [];
$preflightOk = true;
foreach ($targets as $target) {
    $response = $asaas->getPayment($target['id']);
    $data = is_array($response['data'] ?? null) ? $response['data'] : [];
    $matched = ($response['ok'] ?? false)
        && strtoupper((string) ($data['status'] ?? '')) === 'OVERDUE'
        && (string) ($data['customer'] ?? '') === $target['customer']
        && abs(((float) ($data['value'] ?? 0)) - $target['value']) < 0.01
        && !((bool) ($data['deleted'] ?? false));
    if (isset($target['external_reference'])) {
        $matched = $matched
            && (string) ($data['externalReference'] ?? '') === $target['external_reference'];
    }
    $preflight[] = [
        'id' => $target['id'],
        'http_status' => (int) ($response['status'] ?? 0),
        'status' => (string) ($data['status'] ?? ''),
        'matched_expected_state' => $matched,
    ];
    if (!$matched) {
        $preflightOk = false;
    }
}

if (!$preflightOk) {
    Helpers::json([
        'ok' => false,
        'error' => 'Pré-validação falhou; nenhuma alteração foi executada.',
        'preflight' => $preflight,
        'production_mutations' => 0,
    ], 409);
}

$cancellations = [];
$contaminatedPaymentsCanceled = true;
foreach ($targets as $target) {
    $response = $asaas->deletePayment($target['id']);
    $deleted = ($response['ok'] ?? false)
        && (bool) (($response['data']['deleted'] ?? false));
    $cancellations[] = [
        'id' => $target['id'],
        'group' => $target['group'],
        'http_status' => (int) ($response['status'] ?? 0),
        'deleted' => $deleted,
    ];
    if ($target['group'] === 'contaminated' && !$deleted) {
        $contaminatedPaymentsCanceled = false;
    }
}

$customerDeletion = [
    'id' => $contaminatedCustomerId,
    'attempted' => false,
    'deleted' => false,
    'http_status' => 0,
];
if ($contaminatedPaymentsCanceled) {
    $response = $asaas->deleteCustomer($contaminatedCustomerId);
    $customerDeletion = [
        'id' => $contaminatedCustomerId,
        'attempted' => true,
        'deleted' => ($response['ok'] ?? false)
            && (bool) (($response['data']['deleted'] ?? false)),
        'http_status' => (int) ($response['status'] ?? 0),
    ];
}

Helpers::json([
    'ok' => true,
    'executed_at_utc' => gmdate('c'),
    'preflight' => $preflight,
    'cancellations' => $cancellations,
    'customer_deletion' => $customerDeletion,
]);
