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
use App\Services\AsaasPaymentLifecycle;
use App\Services\MonthlyWorkshopService;
use App\SupabaseClient;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Método inválido.');
}

$redirectWithError = static function (string $message): void {
    $_SESSION['financeiro_error'] = $message;
    header('Location: /financeiro.php');
    exit;
};

function parseDateToIsoLocal(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return $raw;
    }
    if (preg_match('/^\d{2}\/\d{2}\/\d{2,4}$/', $raw)) {
        [$day, $month, $year] = explode('/', $raw);
        $yearInt = (int) $year;
        if ($yearInt < 100) {
            $yearInt += 2000;
        }
        if (!checkdate((int) $month, (int) $day, $yearInt)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $yearInt, (int) $month, (int) $day);
    }
    $time = strtotime($raw);
    if ($time === false) {
        return null;
    }
    return date('Y-m-d', $time);
}

function extractIsoDatesFromPaymentLocal(array $payment): array
{
    $dailyRaw = trim((string) ($payment['daily_type'] ?? ''));
    $parts = explode('|', $dailyRaw, 2);
    $datesLabelRaw = trim((string) ($parts[1] ?? ''));
    $dates = [];
    if ($datesLabelRaw !== '') {
        $tokens = array_map('trim', explode(',', str_replace([';', '+'], ',', $datesLabelRaw)));
        foreach ($tokens as $token) {
            $iso = parseDateToIsoLocal((string) $token);
            if ($iso !== null) {
                $dates[$iso] = true;
            }
        }
    }
    if (empty($dates)) {
        $fallback = parseDateToIsoLocal((string) ($payment['payment_date'] ?? ''));
        if ($fallback !== null) {
            $dates[$fallback] = true;
        }
    }
    $keys = array_keys($dates);
    sort($keys);
    return $keys;
}

function resolveDayUseChargeLocal(string $dayUseDate): float
{
    $timestamp = strtotime($dayUseDate);
    if ($timestamp === false) {
        return 77.00;
    }
    $dayUseIso = date('Y-m-d', $timestamp);
    $tz = new DateTimeZone('America/Sao_Paulo');
    $now = new DateTimeImmutable('now', $tz);
    $today = $now->format('Y-m-d');
    $hour = (int) $now->format('H');
    $promoDeadline = '2026-03-16';

    if ($dayUseIso <= $promoDeadline) {
        return 77.00;
    }
    if ($dayUseIso > $today) {
        return 77.00;
    }
    if ($dayUseIso === $today && $hour < 10) {
        return 77.00;
    }
    return 97.00;
}

function resolveExpectedAmountForPayment(array $payment): float
{
    $isoDates = extractIsoDatesFromPaymentLocal($payment);
    if (empty($isoDates)) {
        $stored = (float) ($payment['amount'] ?? 0);
        return $stored > 0 ? $stored : 77.00;
    }
    $total = 0.0;
    foreach ($isoDates as $isoDate) {
        $total += resolveDayUseChargeLocal((string) $isoDate);
    }
    return $total > 0 ? $total : 77.00;
}

$user = Helpers::requireAuthWeb();
$csrfToken = trim((string) ($_POST['csrf_token'] ?? ''));
$expectedCsrfToken = trim((string) ($_SESSION['financeiro_csrf_token'] ?? ''));
if (
    $csrfToken === ''
    || $expectedCsrfToken === ''
    || !hash_equals($expectedCsrfToken, $csrfToken)
) {
    $redirectWithError('Sessão de pagamento expirada. Reabra o financeiro.');
}
$paymentId = trim((string) ($_POST['payment_id'] ?? ''));
if ($paymentId === '') {
    $redirectWithError('Cobrança não informada.');
}

$client = new SupabaseClient(new HttpClient());
$asaas = new AsaasClient(new HttpClient());
$paymentLifecycle = new AsaasPaymentLifecycle($asaas);
$today = date('Y-m-d');

$paymentResult = $client->select(
    'payments',
    'select=id,guardian_id,student_id,payment_date,daily_type,amount,status,billing_type,asaas_payment_id'
    . '&id=eq.' . urlencode($paymentId) . '&limit=1'
);
$paymentRows = ($paymentResult['ok'] ?? false) && is_array($paymentResult['data'] ?? null)
    ? $paymentResult['data']
    : [];
$payment = $paymentRows[0] ?? null;
if (!$payment) {
    $redirectWithError('Cobrança não encontrada.');
}
$expectedAmount = resolveExpectedAmountForPayment((array) $payment);

$sessionGuardianId = trim((string) ($user['id'] ?? ''));
$sessionStudentId = trim((string) ($user['student_id'] ?? ''));
$paymentGuardianId = trim((string) ($payment['guardian_id'] ?? ''));
$paymentStudentId = trim((string) ($payment['student_id'] ?? ''));
if (
    $sessionGuardianId === ''
    || $sessionStudentId === ''
    || $paymentGuardianId === ''
    || $paymentStudentId === ''
    || !hash_equals($sessionGuardianId, $paymentGuardianId)
    || !hash_equals($sessionStudentId, $paymentStudentId)
) {
    $redirectWithError('Você não tem permissão para pagar esta cobrança.');
}
if ($paymentStudentId !== '' && (new MonthlyWorkshopService($client))->getActivePlan($paymentStudentId) !== null) {
    $redirectWithError('Aluno mensalista não gera PIX. Confirme as oficinas no fluxo mensal.');
}

$statusRaw = strtolower(trim((string) ($payment['status'] ?? '')));
if (in_array($statusRaw, ['paid', 'canceled', 'cancelled', 'refunded', 'deleted'], true)) {
    $redirectWithError('Esta cobrança não está mais pendente de pagamento.');
}
if ($statusRaw === 'processing_asaas') {
    $redirectWithError('Esta cobrança já está sendo processada. Aguarde alguns instantes.');
}
if (!in_array($statusRaw, ['queued', 'pending', 'pending_asaas', 'overdue', 'awaiting_risk_analysis'], true)) {
    $redirectWithError('Estado local desconhecido. Operação bloqueada para revisão.');
}

$guardianResult = $client->select(
    'guardians',
    'select=id,parent_name,parent_phone,parent_document,email,asaas_customer_id'
    . '&id=eq.' . rawurlencode($paymentGuardianId)
    . '&student_id=eq.' . rawurlencode($paymentStudentId)
    . '&limit=1'
);
$guardian = $guardianResult['data'][0] ?? null;
if (!$guardian) {
    $redirectWithError('Responsável não encontrado para esta cobrança.');
}

$identity = (new \App\AsaasCustomerIdentity($asaas, $client))->resolve($guardian);
if (!($identity['ok'] ?? false)) {
    $redirectWithError((string) ($identity['error'] ?? 'Falha ao validar o responsável no Asaas.'));
}
$customerId = (string) $identity['customer_id'];

$existingAsaasPaymentId = trim((string) ($payment['asaas_payment_id'] ?? ''));
$invoiceUrl = '';
$shouldCreateNew = $existingAsaasPaymentId === '';
$effectiveAsaasPaymentId = $existingAsaasPaymentId;
$existingPaymentResponse = null;

if ($existingAsaasPaymentId !== '') {
    $asaasPaymentResponse = $asaas->getPayment($existingAsaasPaymentId);
    if (!($asaasPaymentResponse['ok'] ?? false) && (int) ($asaasPaymentResponse['status'] ?? 0) === 404) {
        $asaasPaymentResponse = [
            'ok' => true,
            'status' => 200,
            'data' => ['status' => 'DELETED', 'customer' => $customerId],
        ];
    }
    if (!($asaasPaymentResponse['ok'] ?? false) || !is_array($asaasPaymentResponse['data'] ?? null)) {
        $redirectWithError('Não foi possível confirmar a cobrança atual no Asaas. Tente novamente em instantes.');
    }
    $existingPaymentResponse = $asaasPaymentResponse;
    $asaasData = $asaasPaymentResponse['data'];
    $asaasStatus = strtoupper(trim((string) ($asaasData['status'] ?? '')));
    $remoteCustomerId = trim((string) ($asaasData['customer'] ?? ''));
    $asaasValue = (float) ($asaasData['value'] ?? 0);
    if ($remoteCustomerId === '' || !hash_equals($customerId, $remoteCustomerId)) {
        $redirectWithError('A cobrança atual não pertence ao responsável validado. Operação bloqueada.');
    }
    if (in_array($asaasStatus, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'], true)) {
        if ($asaasValue <= 0 || ($expectedAmount > 0 && abs($asaasValue - $expectedAmount) > 0.009)) {
            $redirectWithError('O valor recebido no Asaas diverge da cobrança local. Operação bloqueada.');
        }
        $paidUpdate = $client->update(
            'payments',
            'id=eq.' . rawurlencode($paymentId)
                . '&guardian_id=eq.' . rawurlencode($paymentGuardianId)
                . '&student_id=eq.' . rawurlencode($paymentStudentId)
                . '&asaas_payment_id=eq.' . rawurlencode($existingAsaasPaymentId)
                . '&status=eq.' . rawurlencode($statusRaw),
            ['status' => 'paid', 'paid_at' => date('c')]
        );
        if (!($paidUpdate['ok'] ?? false) || empty($paidUpdate['data'][0])) {
            $redirectWithError('Pagamento confirmado no Asaas, mas a atualização local falhou. Contate o atendimento.');
        }
        header('Location: /financeiro.php');
        exit;
    }

    $invoiceUrl = trim((string) ($asaasData['invoiceUrl'] ?? ($asaasData['bankSlipUrl'] ?? '')));
    $closedStatuses = ['CANCELED', 'CANCELLED', 'DELETED', 'REFUNDED', 'REFUND_REQUESTED', 'REFUND_IN_PROGRESS'];
    $reusableStatuses = ['PENDING', 'OVERDUE', 'AWAITING_RISK_ANALYSIS'];
    if (in_array($asaasStatus, $closedStatuses, true)) {
        $shouldCreateNew = true;
        $invoiceUrl = '';
    } elseif (!in_array($asaasStatus, $reusableStatuses, true)) {
        $redirectWithError('Estado desconhecido da cobrança no Asaas. Operação bloqueada para revisão.');
    } elseif ($invoiceUrl === '') {
        $shouldCreateNew = true;
    } elseif ($asaasValue <= 0 || ($expectedAmount > 0 && abs($asaasValue - $expectedAmount) > 0.009)) {
        $shouldCreateNew = true;
    } else {
        $shouldCreateNew = false;
    }
}

$amount = $expectedAmount > 0 ? $expectedAmount : 77.00;
$dailyTypeRaw = strtolower(trim((string) (explode('|', (string) ($payment['daily_type'] ?? ''), 2)[0] ?? 'planejada')));
$dailyBaseType = $dailyTypeRaw === 'emergencial' ? 'emergencial' : 'planejada';

if ($shouldCreateNew) {
    if (
        $existingAsaasPaymentId === ''
        && ($statusRaw !== 'queued' || strtoupper((string) ($payment['billing_type'] ?? '')) !== 'PIX_MANUAL_QUEUE')
    ) {
        $redirectWithError('Cobrança sem vínculo Asaas em estado inconsistente. Contate o atendimento.');
    }

    $operationToken = bin2hex(random_bytes(16));
    $claim = $client->update(
        'payments',
        'id=eq.' . urlencode($paymentId) . '&status=eq.' . urlencode($statusRaw),
        [
            'status' => 'processing_asaas',
            'asaas_operation_token' => $operationToken,
        ]
    );
    if (!($claim['ok'] ?? false) || empty($claim['data'][0])) {
        $redirectWithError('Esta cobrança já está sendo processada. Aguarde alguns instantes.');
    }
    $remoteMutationOccurred = false;
    $releaseClaimIfSafe = static function () use (
        $client,
        $paymentId,
        $statusRaw,
        $operationToken,
        &$remoteMutationOccurred
    ): void {
        if ($remoteMutationOccurred) {
            return;
        }
        $client->update(
            'payments',
            'id=eq.' . urlencode($paymentId)
                . '&status=eq.processing_asaas&asaas_operation_token=eq.' . urlencode($operationToken),
            [
                'status' => $statusRaw,
                'asaas_operation_token' => null,
            ]
        );
    };

    if ($existingAsaasPaymentId !== '' && is_array($existingPaymentResponse)) {
        $cancelOld = $paymentLifecycle->cancelBeforeLocalMutation(
            $existingAsaasPaymentId,
            $existingPaymentResponse,
            $guardian
        );
        if (!($cancelOld['ok'] ?? false)) {
            $releaseClaimIfSafe();
            $redirectWithError((string) ($cancelOld['error'] ?? 'Não foi possível cancelar a cobrança anterior.'));
        }
        $remoteMutationOccurred = true;
    }

    $createPaymentResponse = $asaas->createPayment([
        'customer' => $customerId,
        'billingType' => 'PIX',
        'value' => $amount,
        'dueDate' => $today,
        'description' => 'Diária ' . $dailyBaseType . ' - pagamento pelo financeiro - Einstein Village',
        'externalReference' => 'payment:' . $paymentId . ':' . $operationToken,
    ]);
    if (!($createPaymentResponse['ok'] ?? false)) {
        if ($paymentLifecycle->isDefinitiveCreationRejection($createPaymentResponse)) {
            $releaseClaimIfSafe();
            $redirectWithError('O Asaas rejeitou a nova cobrança. Revise os dados e tente novamente.');
        }
        $redirectWithError('O resultado da criação ficou incerto. Operação bloqueada para conciliação segura.');
    }
    $remoteMutationOccurred = true;
    $asaasNewData = is_array($createPaymentResponse['data'] ?? null) ? $createPaymentResponse['data'] : [];
    $invoiceUrl = trim((string) ($asaasNewData['invoiceUrl'] ?? ($asaasNewData['bankSlipUrl'] ?? '')));
    $effectiveAsaasPaymentId = trim((string) ($asaasNewData['id'] ?? ''));
    if ($effectiveAsaasPaymentId === '' || $invoiceUrl === '') {
        $compensation = $paymentLifecycle->compensateCreatedPayment($effectiveAsaasPaymentId);
        $message = ($compensation['ok'] ?? false)
            ? 'O Asaas retornou uma cobrança inválida; ela foi cancelada. Tente novamente.'
            : 'O Asaas retornou uma cobrança inválida e a compensação falhou. Contate o atendimento.';
        $redirectWithError($message);
    }
    $localUpdate = $client->update(
        'payments',
        'id=eq.' . urlencode($paymentId)
            . '&status=eq.processing_asaas&asaas_operation_token=eq.' . urlencode($operationToken),
        [
        'status' => 'pending_asaas',
        'billing_type' => 'PIX_MANUAL',
        'asaas_payment_id' => $effectiveAsaasPaymentId,
        'asaas_operation_token' => null,
        ]
    );
    if (!($localUpdate['ok'] ?? false) || empty($localUpdate['data'][0])) {
        $compensation = $paymentLifecycle->compensateCreatedPayment($effectiveAsaasPaymentId);
        $message = ($compensation['ok'] ?? false)
            ? 'A atualização local falhou e a nova cobrança foi cancelada. Tente novamente.'
            : 'A atualização local e o cancelamento compensatório falharam. Contate o atendimento.';
        $redirectWithError($message);
    }
}

if ($invoiceUrl === '') {
    $redirectWithError('Link de pagamento indisponível no momento. Tente novamente.');
}
$invoiceParts = filter_var($invoiceUrl, FILTER_VALIDATE_URL) ? parse_url($invoiceUrl) : false;
$invoiceScheme = is_array($invoiceParts) ? strtolower((string) ($invoiceParts['scheme'] ?? '')) : '';
$invoiceHost = is_array($invoiceParts) ? strtolower((string) ($invoiceParts['host'] ?? '')) : '';
if (
    $invoiceScheme !== 'https'
    || ($invoiceHost !== 'asaas.com' && !str_ends_with($invoiceHost, '.asaas.com'))
) {
    $redirectWithError('O Asaas retornou um link de pagamento inválido.');
}

header('Location: ' . $invoiceUrl);
exit;
