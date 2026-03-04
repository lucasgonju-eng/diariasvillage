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
use App\SupabaseClient;

function is_paid_status(string $status): bool
{
    return in_array($status, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH', 'PAID'], true);
}

function normalize_digits(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

Helpers::requirePost();

$client = new SupabaseClient(new HttpClient());
$asaas = new AsaasClient(new HttpClient());
$summary = [
    'payments_checked' => 0,
    'payments_promoted_paid' => 0,
    'pendencias_checked' => 0,
    'pendencias_promoted_paid' => 0,
];

$paymentsResult = $client->select(
    'payments',
    'select=id,status,paid_at,asaas_payment_id&status=neq.paid&asaas_payment_id=not.is.null&limit=5000'
);
$payments = ($paymentsResult['ok'] ?? false) && is_array($paymentsResult['data'] ?? null) ? $paymentsResult['data'] : [];
foreach ($payments as $payment) {
    $summary['payments_checked']++;
    $paymentId = trim((string) ($payment['id'] ?? ''));
    $asaasPaymentId = trim((string) ($payment['asaas_payment_id'] ?? ''));
    if ($paymentId === '' || $asaasPaymentId === '') {
        continue;
    }
    $response = $asaas->getPayment($asaasPaymentId);
    $asaasData = ($response['ok'] ?? false) ? ($response['data'] ?? null) : null;
    if (!is_array($asaasData)) {
        continue;
    }
    if (!is_paid_status((string) ($asaasData['status'] ?? ''))) {
        continue;
    }
    $update = $client->update('payments', 'id=eq.' . urlencode($paymentId), [
        'status' => 'paid',
        'paid_at' => date('c'),
    ]);
    if ($update['ok'] ?? false) {
        $summary['payments_promoted_paid']++;
    }
}

$pendenciasResult = $client->select(
    'pendencia_de_cadastro',
    'select=id,paid_at,payment_date,guardian_cpf,guardian_email,asaas_payment_id,asaas_invoice_url&paid_at=is.null&limit=5000'
);
$pendencias = ($pendenciasResult['ok'] ?? false) && is_array($pendenciasResult['data'] ?? null) ? $pendenciasResult['data'] : [];
foreach ($pendencias as $pendencia) {
    $summary['pendencias_checked']++;
    $pendenciaId = trim((string) ($pendencia['id'] ?? ''));
    $asaasPaymentId = trim((string) ($pendencia['asaas_payment_id'] ?? ''));
    $invoiceUrl = trim((string) ($pendencia['asaas_invoice_url'] ?? ''));
    $paymentDateRaw = trim((string) ($pendencia['payment_date'] ?? ''));
    $paymentDate = $paymentDateRaw !== '' ? date('Y-m-d', strtotime($paymentDateRaw)) : '';
    $candidate = null;

    if ($asaasPaymentId !== '') {
        $response = $asaas->getPayment($asaasPaymentId);
        $candidate = ($response['ok'] ?? false) ? ($response['data'] ?? null) : null;
    }
    if (!is_array($candidate) && $invoiceUrl !== '') {
        $response = $asaas->findPaymentByInvoiceUrl($invoiceUrl);
        $list = ($response['ok'] ?? false) && is_array($response['data']['data'] ?? null) ? $response['data']['data'] : [];
        $candidate = $list[0] ?? null;
    }

    if (!is_array($candidate)) {
        $paymentsPool = [];
        $guardianCpf = normalize_digits((string) ($pendencia['guardian_cpf'] ?? ''));
        $guardianEmail = trim((string) ($pendencia['guardian_email'] ?? ''));
        $customerIds = [];
        if ($guardianCpf !== '') {
            $res = $asaas->findCustomersByCpfCnpj($guardianCpf);
            $customers = ($res['ok'] ?? false) && is_array($res['data']['data'] ?? null) ? $res['data']['data'] : [];
            foreach ($customers as $customer) {
                $cid = trim((string) ($customer['id'] ?? ''));
                if ($cid !== '') $customerIds[$cid] = true;
            }
        }
        if ($guardianEmail !== '') {
            $res = $asaas->findCustomersByEmail($guardianEmail);
            $customers = ($res['ok'] ?? false) && is_array($res['data']['data'] ?? null) ? $res['data']['data'] : [];
            foreach ($customers as $customer) {
                $cid = trim((string) ($customer['id'] ?? ''));
                if ($cid !== '') $customerIds[$cid] = true;
            }
        }
        foreach (array_keys($customerIds) as $customerId) {
            $res = $asaas->listPaymentsByCustomer($customerId, 100, 0);
            $list = ($res['ok'] ?? false) && is_array($res['data']['data'] ?? null) ? $res['data']['data'] : [];
            foreach ($list as $item) {
                if (!is_array($item) || !is_paid_status((string) ($item['status'] ?? ''))) {
                    continue;
                }
                if ($paymentDate !== '') {
                    $dueDateRaw = trim((string) ($item['dueDate'] ?? ''));
                    $dueDate = $dueDateRaw !== '' ? date('Y-m-d', strtotime($dueDateRaw)) : '';
                    if ($dueDate !== '' && $dueDate !== $paymentDate) {
                        continue;
                    }
                }
                $paymentsPool[] = $item;
            }
        }
        if (!empty($paymentsPool)) {
            usort($paymentsPool, static function ($a, $b): int {
                $aDate = strtotime((string) ($a['clientPaymentDate'] ?? '')) ?: 0;
                $bDate = strtotime((string) ($b['clientPaymentDate'] ?? '')) ?: 0;
                return $bDate <=> $aDate;
            });
            $candidate = $paymentsPool[0];
        }
    }

    if (!is_array($candidate) || !is_paid_status((string) ($candidate['status'] ?? ''))) {
        continue;
    }
    if ($pendenciaId === '') {
        continue;
    }
    $update = $client->update('pendencia_de_cadastro', 'id=eq.' . urlencode($pendenciaId), [
        'paid_at' => date('c'),
        'asaas_payment_id' => $candidate['id'] ?? ($asaasPaymentId !== '' ? $asaasPaymentId : null),
        'asaas_invoice_url' => $candidate['invoiceUrl'] ?? ($candidate['bankSlipUrl'] ?? ($invoiceUrl !== '' ? $invoiceUrl : null)),
    ]);
    if ($update['ok'] ?? false) {
        $summary['pendencias_promoted_paid']++;
    }
}

Helpers::json(['ok' => true, 'summary' => $summary]);
