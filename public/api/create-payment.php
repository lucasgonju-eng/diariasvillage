<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\AsaasClient;
use App\Helpers;
use App\HttpClient;
use App\Mailer;
use App\SupabaseClient;

Helpers::requirePost();
$user = Helpers::requireAuth();
$payload = json_decode(file_get_contents('php://input'), true);

$date = $payload['date'] ?? '';
$billingType = $payload['billing_type'] ?? 'PIX';
$documentRaw = trim($payload['document'] ?? '');

if ($date === '') {
    Helpers::json(['ok' => false, 'error' => 'Selecione a data.'], 422);
}

$today = date('Y-m-d');
$hour = (int) date('H');
$plannedAmount = 77.00;

if ($date === $today) {
    if ($hour >= 16) {
        Helpers::json([
            'ok' => false,
            'error' => 'Compras para hoje encerradas apos as 16h. Escolha uma data futura.',
        ], 422);
    }

    if ($hour < 10) {
        $dailyType = 'planejada';
        $amount = $plannedAmount;
    } else {
        $dailyType = 'emergencial';
        $amount = 97.00;
    }
} else {
    $dailyType = 'planejada';
    $amount = $plannedAmount;
}

if (!in_array($billingType, ['PIX', 'DEBIT_CARD'], true)) {
    Helpers::json(['ok' => false, 'error' => 'Forma de pagamento invalida.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$guardian = $client->select('guardians', 'select=*&id=eq.' . $user['id']);
if (!$guardian['ok'] || empty($guardian['data'])) {
    Helpers::json(['ok' => false, 'error' => 'Usuario nao encontrado.'], 404);
}

$guardianData = $guardian['data'][0];
$asaas = new AsaasClient(new HttpClient());

if ($documentRaw === '') {
    Helpers::json([
        'ok' => false,
        'error' => 'Informe um CPF ou CNPJ valido para gerar o pagamento.',
    ], 422);
}

$document = preg_replace('/\D+/', '', $documentRaw);

if (empty($guardianData['asaas_customer_id'])) {
    $customerPayload = [
        'name' => $guardianData['parent_name'] ?: 'Responsavel ' . $guardianData['email'],
        'email' => $guardianData['email'],
    ];

    if ($document !== '') {
        $customerPayload['cpfCnpj'] = $document;
    }

    $customer = $asaas->createCustomer($customerPayload);

    if (!$customer['ok']) {
        $logPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'error_log_custom.txt';
        file_put_contents($logPath, 'Asaas createCustomer error: ' . json_encode($customer) . PHP_EOL, FILE_APPEND);
        error_log('Asaas createCustomer error: ' . json_encode($customer));
        Helpers::json(['ok' => false, 'error' => 'Falha ao criar cliente na Asaas.'], 500);
    }

    $guardianData['asaas_customer_id'] = $customer['data']['id'] ?? null;
    $client->update('guardians', 'id=eq.' . $guardianData['id'], [
        'asaas_customer_id' => $guardianData['asaas_customer_id'],
        'parent_document' => $document,
    ]);
} else {
    $asaas->updateCustomer($guardianData['asaas_customer_id'], [
        'cpfCnpj' => $document,
    ]);
    $client->update('guardians', 'id=eq.' . $guardianData['id'], [
        'parent_document' => $document,
    ]);
}

$payment = $asaas->createPayment([
    'customer' => $guardianData['asaas_customer_id'],
    'billingType' => $billingType,
    'value' => $amount,
    'dueDate' => $date,
    'description' => 'Diaria ' . $dailyType . ' - Einstein Village',
]);

if (!$payment['ok']) {
    $logPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'error_log_custom.txt';
    file_put_contents($logPath, 'Asaas createPayment error: ' . json_encode($payment) . PHP_EOL, FILE_APPEND);
    error_log('Asaas createPayment error: ' . json_encode($payment));
    Helpers::json(['ok' => false, 'error' => 'Falha ao criar pagamento.'], 500);
}

$paymentData = $payment['data'];
$client->insert('payments', [[
    'guardian_id' => $guardianData['id'],
    'student_id' => $guardianData['student_id'],
    'payment_date' => $date,
    'daily_type' => $dailyType,
    'amount' => $amount,
    'status' => 'pending',
    'billing_type' => $billingType,
    'asaas_payment_id' => $paymentData['id'] ?? null,
]]);

$invoiceUrl = $paymentData['invoiceUrl'] ?? $paymentData['bankSlipUrl'] ?? '#';

$mailer = new Mailer();
$mailer->send(
    $guardianData['email'],
    'Pagamento criado - Diarias Village',
    '<p>Seu pagamento foi criado. Acesse o link abaixo para finalizar:</p>'
    . '<p><a href="' . $invoiceUrl . '">Pagar diaria</a></p>'
);

Helpers::json(['ok' => true, 'invoice_url' => $invoiceUrl]);
