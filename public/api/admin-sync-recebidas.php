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

function pick_non_empty(array $arr, array $keys): string
{
    foreach ($keys as $key) {
        $value = trim((string) ($arr[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }
    return '';
}

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

Helpers::requirePost();

$client = new SupabaseClient(new HttpClient());
$asaas = new AsaasClient(new HttpClient());
$summary = [
    'payments_checked_local' => 0,
    'payments_promoted_paid' => 0,
    'pendencias_checked' => 0,
    'pendencias_promoted_paid' => 0,
    'asaas_scanned_total' => 0,
    'asaas_paid_found' => 0,
    'asaas_paid_imported_payments' => 0,
    'asaas_paid_imported_pendencias' => 0,
    'asaas_paid_unmapped' => 0,
];

// 1) Promove para pago itens locais já vinculados.
$paymentsResult = $client->select(
    'payments',
    'select=id,status,paid_at,asaas_payment_id&status=neq.paid&asaas_payment_id=not.is.null&limit=5000'
);
$payments = ($paymentsResult['ok'] ?? false) && is_array($paymentsResult['data'] ?? null) ? $paymentsResult['data'] : [];
foreach ($payments as $payment) {
    $summary['payments_checked_local']++;
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

// 2) Promove pendências locais já vinculadas.
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

// 3) Asaas-first real: varre TODAS as cobranças no Asaas e filtra pagas.
$paidPayments = [];
$offset = 0;
for ($page = 0; $page < 40; $page++) {
    $res = $asaas->listPayments(100, $offset);
    $list = ($res['ok'] ?? false) && is_array($res['data']['data'] ?? null) ? $res['data']['data'] : [];
    if (empty($list)) {
        break;
    }
    foreach ($list as $payment) {
        if (!is_array($payment)) {
            continue;
        }
        $summary['asaas_scanned_total']++;
        if (!is_paid_status((string) ($payment['status'] ?? ''))) {
            continue;
        }
        $asaasPaymentId = trim((string) ($payment['id'] ?? ''));
        if ($asaasPaymentId === '') {
            continue;
        }
        $paidPayments[$asaasPaymentId] = $payment;
    }
    if (count($list) < 100) {
        break;
    }
    $offset += 100;
}
$summary['asaas_paid_found'] = count($paidPayments);

$customerCache = [];
foreach ($paidPayments as $asaasPaymentId => $payment) {
    // a) Já existe em payments: só confirma como pago.
    $existingPayment = $client->select(
        'payments',
        'select=id,status,paid_at&asaas_payment_id=eq.' . urlencode($asaasPaymentId) . '&limit=1'
    );
    $existingRow = $existingPayment['data'][0] ?? null;
    if ($existingRow) {
        if (($existingRow['status'] ?? '') !== 'paid' || empty($existingRow['paid_at'])) {
            $paidAtRaw = pick_non_empty($payment, ['clientPaymentDate', 'paymentDate', 'confirmedDate']);
            $paidAt = $paidAtRaw !== '' ? date('c', strtotime($paidAtRaw)) : date('c');
            $update = $client->update('payments', 'id=eq.' . urlencode((string) $existingRow['id']), [
                'status' => 'paid',
                'paid_at' => $paidAt,
            ]);
            if ($update['ok'] ?? false) {
                $summary['payments_promoted_paid']++;
            }
        }
        continue;
    }

    // b) Já existe pendência desse asaas id: marca paga.
    $existingPend = $client->select(
        'pendencia_de_cadastro',
        'select=id,paid_at&asaas_payment_id=eq.' . urlencode($asaasPaymentId) . '&limit=1'
    );
    $pendRow = $existingPend['data'][0] ?? null;
    if ($pendRow) {
        if (empty($pendRow['paid_at'])) {
            $paidAtRaw = pick_non_empty($payment, ['clientPaymentDate', 'paymentDate', 'confirmedDate']);
            $paidAt = $paidAtRaw !== '' ? date('c', strtotime($paidAtRaw)) : date('c');
            $invoiceUrl = pick_non_empty($payment, ['invoiceUrl', 'bankSlipUrl']);
            $update = $client->update('pendencia_de_cadastro', 'id=eq.' . urlencode((string) $pendRow['id']), [
                'paid_at' => $paidAt,
                'asaas_invoice_url' => $invoiceUrl !== '' ? $invoiceUrl : null,
            ]);
            if ($update['ok'] ?? false) {
                $summary['pendencias_promoted_paid']++;
            }
        }
        continue;
    }

    // c) Tenta mapear responsável/aluno.
    $customerId = trim((string) ($payment['customer'] ?? ''));
    $guardian = null;
    $customer = null;

    if ($customerId !== '') {
        $guardianRes = $client->select(
            'guardians',
            'select=id,student_id,email,parent_document&asaas_customer_id=eq.' . urlencode($customerId) . '&limit=1'
        );
        $guardian = $guardianRes['data'][0] ?? null;
    }

    if ($customerId !== '' && !array_key_exists($customerId, $customerCache)) {
        $customerRes = $asaas->getCustomer($customerId);
        $customerCache[$customerId] = (($customerRes['ok'] ?? false) && is_array($customerRes['data'] ?? null))
            ? $customerRes['data']
            : null;
    }
    if ($customerId !== '') {
        $customer = $customerCache[$customerId] ?? null;
    }

    if (!$guardian && is_array($customer)) {
        $cpf = normalize_digits((string) ($customer['cpfCnpj'] ?? ''));
        $email = trim((string) ($customer['email'] ?? ''));
        if ($cpf !== '') {
            $guardianRes = $client->select(
                'guardians',
                'select=id,student_id,email,parent_document&parent_document=eq.' . urlencode($cpf) . '&limit=1'
            );
            $guardian = $guardianRes['data'][0] ?? null;
            if (!$guardian) {
                $guardianRes = $client->select(
                    'guardians',
                    'select=id,student_id,email,parent_document&parent_document=ilike.' . urlencode('%' . $cpf . '%') . '&limit=1'
                );
                $guardian = $guardianRes['data'][0] ?? null;
            }
        }
        if (!$guardian && $email !== '') {
            $guardianRes = $client->select(
                'guardians',
                'select=id,student_id,email,parent_document&email=eq.' . urlencode($email) . '&limit=1'
            );
            $guardian = $guardianRes['data'][0] ?? null;
        }
        if ($guardian && $customerId !== '') {
            $client->update('guardians', 'id=eq.' . urlencode((string) $guardian['id']), [
                'asaas_customer_id' => $customerId,
            ]);
        }
    }

    if ($guardian && !empty($guardian['id']) && !empty($guardian['student_id'])) {
        $dueDateRaw = pick_non_empty($payment, ['dueDate', 'originalDueDate']);
        $paymentDate = $dueDateRaw !== '' ? date('Y-m-d', strtotime($dueDateRaw)) : date('Y-m-d');
        $description = trim((string) ($payment['description'] ?? ''));
        $descLower = function_exists('mb_strtolower') ? mb_strtolower($description, 'UTF-8') : strtolower($description);
        $dailyBase = str_contains($descLower, 'emergencial') ? 'emergencial' : (str_contains($descLower, 'pend') ? 'pendencia' : 'planejada');
        $dailyType = $dailyBase . '|' . date('d/m/y', strtotime($paymentDate));
        $amount = (float) ($payment['value'] ?? 0);
        $billingType = trim((string) ($payment['billingType'] ?? 'PIX'));
        $paidAtRaw = pick_non_empty($payment, ['clientPaymentDate', 'paymentDate', 'confirmedDate']);
        $paidAt = $paidAtRaw !== '' ? date('c', strtotime($paidAtRaw)) : date('c');

        $insertPayment = $client->insert('payments', [[
            'guardian_id' => $guardian['id'],
            'student_id' => $guardian['student_id'],
            'payment_date' => $paymentDate,
            'daily_type' => $dailyType,
            'amount' => $amount,
            'status' => 'paid',
            'billing_type' => $billingType !== '' ? $billingType : 'PIX',
            'asaas_payment_id' => $asaasPaymentId,
            'paid_at' => $paidAt,
        ]]);
        if ($insertPayment['ok'] ?? false) {
            $summary['asaas_paid_imported_payments']++;
            continue;
        }
    }

    // d) Sem mapeamento completo: registra como pendência paga para aparecer em "Cobranças recebidas".
    $invoiceUrl = pick_non_empty($payment, ['invoiceUrl', 'bankSlipUrl']);
    $paidAtRaw = pick_non_empty($payment, ['clientPaymentDate', 'paymentDate', 'confirmedDate']);
    $paidAt = $paidAtRaw !== '' ? date('c', strtotime($paidAtRaw)) : date('c');
    $customerName = is_array($customer) ? trim((string) ($customer['name'] ?? '')) : '';
    $customerEmail = is_array($customer) ? trim((string) ($customer['email'] ?? '')) : '';
    $customerCpf = is_array($customer) ? normalize_digits((string) ($customer['cpfCnpj'] ?? '')) : '';
    if ($customerName === '') {
        $customerName = trim((string) ($payment['customerName'] ?? 'Cliente Asaas'));
    }
    if ($customerEmail === '') {
        $customerEmail = trim((string) ($payment['customerEmail'] ?? ''));
    }
    if ($customerCpf === '') {
        $customerCpf = normalize_digits((string) ($payment['customerCpfCnpj'] ?? ''));
    }
    $guardianCpf = strlen($customerCpf) >= 11 ? substr($customerCpf, 0, 11) : '00000000000';
    $dueDateRaw = pick_non_empty($payment, ['dueDate', 'originalDueDate']);
    $paymentDate = $dueDateRaw !== '' ? date('Y-m-d', strtotime($dueDateRaw)) : date('Y-m-d');

    $insertPend = $client->insert('pendencia_de_cadastro', [[
        'student_name' => $customerName !== '' ? $customerName : 'Cliente Asaas',
        'guardian_name' => $customerName !== '' ? $customerName : 'Responsável',
        'guardian_cpf' => $guardianCpf,
        'guardian_email' => $customerEmail !== '' ? $customerEmail : null,
        'payment_date' => $paymentDate,
        'paid_at' => $paidAt,
        'asaas_payment_id' => $asaasPaymentId,
        'asaas_invoice_url' => $invoiceUrl !== '' ? $invoiceUrl : null,
    ]]);
    if ($insertPend['ok'] ?? false) {
        $summary['asaas_paid_imported_pendencias']++;
    } else {
        $summary['asaas_paid_unmapped']++;
    }
}

Helpers::json(['ok' => true, 'summary' => $summary]);
