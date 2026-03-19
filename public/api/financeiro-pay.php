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
date_default_timezone_set('America/Sao_Paulo');

use App\AsaasClient;
use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    exit('Método inválido.');
}

$redirectWithError = static function (string $message): void {
    $_SESSION['financeiro_error'] = $message;
    header('Location: /financeiro.php');
    exit;
};

$user = Helpers::requireAuthWeb();
$paymentId = trim((string) ($_GET['payment_id'] ?? ''));
if ($paymentId === '') {
    $redirectWithError('Cobrança não informada.');
}

$client = new SupabaseClient(new HttpClient());
$asaas = new AsaasClient(new HttpClient());
$today = date('Y-m-d');

$paymentResult = $client->select(
    'payments',
    'select=id,guardian_id,student_id,payment_date,daily_type,amount,status,billing_type,asaas_payment_id'
    . '&id=eq.' . urlencode($paymentId) . '&limit=1'
);
$payment = $paymentResult['data'][0] ?? null;
if (!$payment) {
    $redirectWithError('Cobrança não encontrada.');
}

$sessionGuardianId = trim((string) ($user['id'] ?? ''));
$sessionStudentId = trim((string) ($user['student_id'] ?? ''));
$paymentGuardianId = trim((string) ($payment['guardian_id'] ?? ''));
$paymentStudentId = trim((string) ($payment['student_id'] ?? ''));
$allowed = false;
if ($sessionGuardianId !== '' && $paymentGuardianId !== '' && $sessionGuardianId === $paymentGuardianId) {
    $allowed = true;
}
if ($sessionStudentId !== '' && $paymentStudentId !== '' && $sessionStudentId === $paymentStudentId) {
    $allowed = true;
}
if (!$allowed) {
    $redirectWithError('Você não tem permissão para pagar esta cobrança.');
}

$statusRaw = strtolower(trim((string) ($payment['status'] ?? '')));
if (in_array($statusRaw, ['paid', 'canceled', 'cancelled', 'refunded', 'deleted'], true)) {
    $redirectWithError('Esta cobrança não está mais pendente de pagamento.');
}

$guardianResult = $client->select(
    'guardians',
    'select=id,parent_name,parent_phone,parent_document,email,asaas_customer_id'
    . '&id=eq.' . urlencode($paymentGuardianId) . '&limit=1'
);
$guardian = $guardianResult['data'][0] ?? null;
if (!$guardian) {
    $redirectWithError('Responsável não encontrado para esta cobrança.');
}

$guardianEmail = trim((string) ($guardian['email'] ?? ''));
$guardianName = trim((string) ($guardian['parent_name'] ?? 'Responsável'));
$guardianDoc = preg_replace('/\D+/', '', (string) ($guardian['parent_document'] ?? '')) ?? '';
$guardianPhone = preg_replace('/\D+/', '', (string) ($guardian['parent_phone'] ?? '')) ?? '';
$customerId = trim((string) ($guardian['asaas_customer_id'] ?? ''));

if ($customerId === '') {
    $customerPayload = ['name' => $guardianName];
    if ($guardianEmail !== '') {
        $customerPayload['email'] = $guardianEmail;
    }
    if ($guardianDoc !== '') {
        $customerPayload['cpfCnpj'] = $guardianDoc;
    }
    if ($guardianPhone !== '') {
        $customerPayload['mobilePhone'] = $guardianPhone;
    }
    $customerResponse = $asaas->createCustomer($customerPayload);
    if (!($customerResponse['ok'] ?? false)) {
        $redirectWithError('Falha ao sincronizar responsável no Asaas. Tente novamente.');
    }
    $customerId = trim((string) ($customerResponse['data']['id'] ?? ''));
    if ($customerId === '') {
        $redirectWithError('Cliente Asaas inválido para esta cobrança.');
    }
    $client->update('guardians', 'id=eq.' . urlencode((string) $guardian['id']), [
        'asaas_customer_id' => $customerId,
    ]);
}

$existingAsaasPaymentId = trim((string) ($payment['asaas_payment_id'] ?? ''));
$invoiceUrl = '';
$shouldCreateNew = $existingAsaasPaymentId === '';
$effectiveAsaasPaymentId = $existingAsaasPaymentId;

if ($existingAsaasPaymentId !== '') {
    $asaasPaymentResponse = $asaas->getPayment($existingAsaasPaymentId);
    if (($asaasPaymentResponse['ok'] ?? false) && is_array($asaasPaymentResponse['data'] ?? null)) {
        $asaasData = $asaasPaymentResponse['data'];
        $asaasStatus = strtoupper(trim((string) ($asaasData['status'] ?? '')));
        if (in_array($asaasStatus, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'], true)) {
            $client->update('payments', 'id=eq.' . urlencode($paymentId), ['status' => 'paid', 'paid_at' => date('c')]);
            header('Location: /financeiro.php');
            exit;
        }
        $invoiceUrl = trim((string) ($asaasData['invoiceUrl'] ?? ($asaasData['bankSlipUrl'] ?? '')));
        if ($asaasStatus === 'OVERDUE' || $invoiceUrl === '') {
            $shouldCreateNew = true;
        } else {
            $shouldCreateNew = false;
        }
    } else {
        $shouldCreateNew = true;
    }
}

$amount = (float) ($payment['amount'] ?? 0);
if ($amount <= 0) {
    $amount = 77.00;
}
$dailyTypeRaw = strtolower(trim((string) (explode('|', (string) ($payment['daily_type'] ?? ''), 2)[0] ?? 'planejada')));
$dailyBaseType = $dailyTypeRaw === 'emergencial' ? 'emergencial' : 'planejada';

if ($shouldCreateNew) {
    $createPaymentResponse = $asaas->createPayment([
        'customer' => $customerId,
        'billingType' => 'PIX',
        'value' => $amount,
        'dueDate' => $today,
        'description' => 'Diária ' . $dailyBaseType . ' - pagamento pelo financeiro - Einstein Village',
    ]);
    if (!($createPaymentResponse['ok'] ?? false)) {
        $redirectWithError('Não foi possível gerar o link de pagamento agora. Tente novamente em instantes.');
    }
    $asaasNewData = is_array($createPaymentResponse['data'] ?? null) ? $createPaymentResponse['data'] : [];
    $invoiceUrl = trim((string) ($asaasNewData['invoiceUrl'] ?? ($asaasNewData['bankSlipUrl'] ?? '')));
    $effectiveAsaasPaymentId = trim((string) ($asaasNewData['id'] ?? ''));
    $client->update('payments', 'id=eq.' . urlencode($paymentId), [
        'status' => 'pending_asaas',
        'billing_type' => 'PIX_MANUAL',
        'asaas_payment_id' => $effectiveAsaasPaymentId !== '' ? $effectiveAsaasPaymentId : null,
    ]);
}

if ($invoiceUrl === '') {
    $redirectWithError('Link de pagamento indisponível no momento. Tente novamente.');
}

header('Location: ' . $invoiceUrl);
exit;
