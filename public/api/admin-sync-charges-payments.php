<?php
$bootstrapCandidates = [
    __DIR__ . '/../src/Bootstrap.php',
    dirname(__DIR__, 2) . '/src/Bootstrap.php',
];
$bootstrapLoaded = false;
foreach ($bootstrapCandidates as $bootstrapFile) {
    if (is_file($bootstrapFile)) {
        require_once $bootstrapFile;
        $bootstrapLoaded = true;
        break;
    }
}
if (!$bootstrapLoaded) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Bootstrap não encontrado.']);
    exit;
}

use App\AsaasClient;
use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

function asaas_status_is_paid(string $status): bool
{
    return in_array($status, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH', 'PAID'], true);
}

function asaas_status_is_open(string $status): bool
{
    return in_array($status, ['PENDING', 'OVERDUE', 'AWAITING_RISK_ANALYSIS'], true);
}

function asaas_status_is_canceled(string $status): bool
{
    return in_array($status, ['CANCELED', 'DELETED', 'REFUNDED', 'REFUND_REQUESTED'], true);
}

function normalize_digits(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function normalize_text_key(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (function_exists('mb_strtoupper')) {
        $value = mb_strtoupper($value, 'UTF-8');
    } else {
        $value = strtoupper($value);
    }
    $translit = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
    if ($translit !== false) {
        $value = $translit;
    }
    $value = preg_replace('/[^A-Z0-9]+/', '', $value) ?? '';
    return trim($value);
}

function normalize_email(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }
    return strtolower($value);
}

function to_iso_date(?string $value): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }
    $time = strtotime($raw);
    if ($time === false) {
        return '';
    }
    return date('Y-m-d', $time);
}

function make_key(string $value, string $date): string
{
    if ($value === '' || $date === '') {
        return '';
    }
    return $value . '|' . $date;
}

function add_paid_reference(array &$index, string $key, array $reference): void
{
    if ($key === '') {
        return;
    }
    if (!isset($index[$key])) {
        $index[$key] = $reference;
    }
}

function detect_duplicate_dayuse_pendencias(SupabaseClient $client): array
{
    $paidByStudentDay = [];
    $paidByCpfDay = [];
    $paidByEmailDay = [];

    $paidPaymentsResult = $client->select(
        'payments',
        'select=id,payment_date,paid_at,amount,billing_type,daily_type,asaas_payment_id,students(name),guardians(parent_name,email,parent_document)'
        . '&status=eq.paid&limit=10000'
    );
    $paidPayments = ($paidPaymentsResult['ok'] ?? false) && is_array($paidPaymentsResult['data'] ?? null)
        ? $paidPaymentsResult['data']
        : [];
    foreach ($paidPayments as $row) {
        $date = to_iso_date((string) ($row['payment_date'] ?? ($row['paid_at'] ?? '')));
        if ($date === '') {
            continue;
        }
        $studentName = trim((string) (($row['students']['name'] ?? '')));
        $guardianName = trim((string) (($row['guardians']['parent_name'] ?? '')));
        $guardianEmail = normalize_email((string) (($row['guardians']['email'] ?? '')));
        $guardianCpf = normalize_digits((string) (($row['guardians']['parent_document'] ?? '')));
        $reference = [
            'source' => 'payments_paid',
            'id' => trim((string) ($row['id'] ?? '')),
            'student_name' => $studentName,
            'guardian_name' => $guardianName,
            'payment_date' => $date,
            'paid_at' => trim((string) ($row['paid_at'] ?? '')),
            'paid_amount' => (float) ($row['amount'] ?? 0),
            'paid_billing_type' => trim((string) ($row['billing_type'] ?? '')),
            'paid_daily_type' => trim((string) ($row['daily_type'] ?? '')),
            'paid_asaas_payment_id' => trim((string) ($row['asaas_payment_id'] ?? '')),
        ];
        add_paid_reference($paidByStudentDay, make_key(normalize_text_key($studentName), $date), $reference);
        add_paid_reference($paidByCpfDay, make_key($guardianCpf, $date), $reference);
        add_paid_reference($paidByEmailDay, make_key($guardianEmail, $date), $reference);
    }

    $paidPendenciasResult = $client->select(
        'pendencia_de_cadastro',
        'select=id,student_name,guardian_name,guardian_email,guardian_cpf,payment_date,paid_at,asaas_payment_id'
        . '&paid_at=not.is.null&limit=10000'
    );
    $paidPendencias = ($paidPendenciasResult['ok'] ?? false) && is_array($paidPendenciasResult['data'] ?? null)
        ? $paidPendenciasResult['data']
        : [];
    foreach ($paidPendencias as $row) {
        $date = to_iso_date((string) ($row['payment_date'] ?? ($row['paid_at'] ?? '')));
        if ($date === '') {
            continue;
        }
        $studentName = trim((string) ($row['student_name'] ?? ''));
        $guardianName = trim((string) ($row['guardian_name'] ?? ''));
        $guardianEmail = normalize_email((string) ($row['guardian_email'] ?? ''));
        $guardianCpf = normalize_digits((string) ($row['guardian_cpf'] ?? ''));
        $reference = [
            'source' => 'pendencia_paid',
            'id' => trim((string) ($row['id'] ?? '')),
            'student_name' => $studentName,
            'guardian_name' => $guardianName,
            'payment_date' => $date,
            'paid_at' => trim((string) ($row['paid_at'] ?? '')),
            'paid_amount' => 77.00,
            'paid_billing_type' => 'PIX',
            'paid_daily_type' => 'pendencia',
            'paid_asaas_payment_id' => trim((string) ($row['asaas_payment_id'] ?? '')),
        ];
        add_paid_reference($paidByStudentDay, make_key(normalize_text_key($studentName), $date), $reference);
        add_paid_reference($paidByCpfDay, make_key($guardianCpf, $date), $reference);
        add_paid_reference($paidByEmailDay, make_key($guardianEmail, $date), $reference);
    }

    $pendingResult = $client->select(
        'pendencia_de_cadastro',
        'select=id,student_name,guardian_name,guardian_email,guardian_cpf,payment_date,paid_at'
        . '&paid_at=is.null&limit=10000'
    );
    $pendingRows = ($pendingResult['ok'] ?? false) && is_array($pendingResult['data'] ?? null)
        ? $pendingResult['data']
        : [];

    $duplicates = [];
    foreach ($pendingRows as $row) {
        $pendenciaId = trim((string) ($row['id'] ?? ''));
        if ($pendenciaId === '') {
            continue;
        }
        $date = to_iso_date((string) ($row['payment_date'] ?? ''));
        if ($date === '') {
            continue;
        }
        $studentName = trim((string) ($row['student_name'] ?? ''));
        $guardianName = trim((string) ($row['guardian_name'] ?? ''));
        $guardianEmail = normalize_email((string) ($row['guardian_email'] ?? ''));
        $guardianCpf = normalize_digits((string) ($row['guardian_cpf'] ?? ''));

        $matchType = '';
        $match = null;
        $studentKey = make_key(normalize_text_key($studentName), $date);
        if ($studentKey !== '' && isset($paidByStudentDay[$studentKey])) {
            $matchType = 'student_day';
            $match = $paidByStudentDay[$studentKey];
        }
        if ($match === null) {
            $cpfKey = make_key($guardianCpf, $date);
            if ($cpfKey !== '' && isset($paidByCpfDay[$cpfKey])) {
                $matchType = 'cpf_day';
                $match = $paidByCpfDay[$cpfKey];
            }
        }
        if ($match === null) {
            $emailKey = make_key($guardianEmail, $date);
            if ($emailKey !== '' && isset($paidByEmailDay[$emailKey])) {
                $matchType = 'email_day';
                $match = $paidByEmailDay[$emailKey];
            }
        }
        if ($match === null) {
            continue;
        }

        $duplicates[$pendenciaId] = [
            'pendencia_id' => $pendenciaId,
            'student_name' => $studentName,
            'guardian_name' => $guardianName,
            'guardian_email' => $guardianEmail,
            'guardian_cpf' => $guardianCpf,
            'payment_date' => $date,
            'matched_by' => $matchType,
            'paid_source' => $match['source'] ?? '',
            'paid_reference_id' => $match['id'] ?? '',
            'paid_student_name' => $match['student_name'] ?? '',
            'paid_guardian_name' => $match['guardian_name'] ?? '',
            'paid_payment_date' => $match['payment_date'] ?? '',
            'paid_at' => $match['paid_at'] ?? '',
            'paid_amount' => (float) ($match['paid_amount'] ?? 0),
            'paid_billing_type' => $match['paid_billing_type'] ?? '',
            'paid_daily_type' => $match['paid_daily_type'] ?? '',
            'paid_asaas_payment_id' => $match['paid_asaas_payment_id'] ?? '',
        ];
    }

    return array_values($duplicates);
}

function unique_payments_by_id(array $payments): array
{
    $unique = [];
    $seen = [];
    foreach ($payments as $payment) {
        if (!is_array($payment)) {
            continue;
        }
        $id = trim((string) ($payment['id'] ?? ''));
        if ($id === '' || isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $unique[] = $payment;
    }
    return $unique;
}

function payment_matches_pendencia(array $payment, string $asaasId, string $invoiceUrl, string $paymentDate): bool
{
    $paymentId = trim((string) ($payment['id'] ?? ''));
    $paymentInvoice = trim((string) ($payment['invoiceUrl'] ?? ($payment['bankSlipUrl'] ?? '')));
    if ($asaasId !== '' && $paymentId !== '' && $paymentId === $asaasId) {
        return true;
    }
    if ($invoiceUrl !== '' && $paymentInvoice !== '' && $paymentInvoice === $invoiceUrl) {
        return true;
    }
    if ($paymentDate !== '') {
        $dueDateRaw = trim((string) ($payment['dueDate'] ?? ''));
        $dueDate = $dueDateRaw !== '' ? date('Y-m-d', strtotime($dueDateRaw)) : '';
        if ($dueDate !== '' && $dueDate === $paymentDate) {
            return true;
        }
    }
    return false;
}

function payment_date_matches(array $payment, string $paymentDate): bool
{
    if ($paymentDate === '') {
        return true;
    }
    $dueDateRaw = trim((string) ($payment['dueDate'] ?? ''));
    $dueDate = $dueDateRaw !== '' ? date('Y-m-d', strtotime($dueDateRaw)) : '';
    return $dueDate !== '' && $dueDate === $paymentDate;
}

function pick_best_payment(array $payments, string $paymentDate, bool $preferPaidAt): ?array
{
    if (empty($payments)) {
        return null;
    }
    $withDateMatch = array_values(array_filter($payments, static fn($p) => payment_date_matches((array) $p, $paymentDate)));
    $pool = !empty($withDateMatch) ? $withDateMatch : $payments;
    usort($pool, static function ($a, $b) use ($preferPaidAt): int {
        $key = $preferPaidAt ? 'clientPaymentDate' : 'dateCreated';
        $aDate = strtotime((string) ($a[$key] ?? '')) ?: strtotime((string) ($a['dateCreated'] ?? '')) ?: 0;
        $bDate = strtotime((string) ($b[$key] ?? '')) ?: strtotime((string) ($b['dateCreated'] ?? '')) ?: 0;
        return $bDate <=> $aDate;
    });
    return $pool[0] ?? null;
}

try {
    if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
        Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
    }

    Helpers::requirePost();
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = [];
    }
    $previewDuplicateDayUse = !empty($payload['preview_duplicate_dayuse']);
    $confirmRemoveDuplicateDayUse = !empty($payload['confirm_remove_duplicate_dayuse']);

    $client = new SupabaseClient(new HttpClient());
    $asaas = new AsaasClient(new HttpClient());

    $summary = [
        'payments_checked' => 0,
        'payments_paid_updated' => 0,
        'payments_canceled_updated' => 0,
        'payments_not_found' => 0,
        'pendencias_checked' => 0,
        'pendencias_paid_updated' => 0,
        'pendencias_unlinked' => 0,
        'pendencias_removed_no_charge' => 0,
        'duplicate_dayuse_detected' => 0,
        'pendencias_removed_duplicate_dayuse' => 0,
        'pendencias_remove_duplicate_failed' => 0,
    ];

    $duplicateDayUseItems = detect_duplicate_dayuse_pendencias($client);
    $summary['duplicate_dayuse_detected'] = count($duplicateDayUseItems);

    if ($previewDuplicateDayUse) {
        Helpers::json([
            'ok' => true,
            'preview' => true,
            'requires_confirmation' => $summary['duplicate_dayuse_detected'] > 0,
            'duplicate_dayuse' => [
                'count' => $summary['duplicate_dayuse_detected'],
                'items' => $duplicateDayUseItems,
            ],
        ]);
    }

    if ($confirmRemoveDuplicateDayUse && !empty($duplicateDayUseItems)) {
        foreach ($duplicateDayUseItems as $duplicate) {
            $pendenciaId = trim((string) ($duplicate['pendencia_id'] ?? ''));
            if ($pendenciaId === '') {
                continue;
            }
            $delete = $client->delete('pendencia_de_cadastro', 'id=eq.' . urlencode($pendenciaId));
            if ($delete['ok'] ?? false) {
                $summary['pendencias_removed_duplicate_dayuse']++;
            } else {
                $summary['pendencias_remove_duplicate_failed']++;
            }
        }
    }

    $paymentsResult = $client->select(
        'payments',
        'select=id,status,paid_at,asaas_payment_id&asaas_payment_id=not.is.null&limit=5000'
    );
    $payments = ($paymentsResult['ok'] ?? false) && is_array($paymentsResult['data'] ?? null)
        ? $paymentsResult['data']
        : [];

    foreach ($payments as $payment) {
        $summary['payments_checked']++;
        $paymentId = trim((string) ($payment['id'] ?? ''));
        $asaasId = trim((string) ($payment['asaas_payment_id'] ?? ''));
        if ($paymentId === '' || $asaasId === '') {
            continue;
        }

        $response = $asaas->getPayment($asaasId);
        $asaasData = ($response['ok'] ?? false) ? ($response['data'] ?? null) : null;
        if (!$asaasData || !is_array($asaasData)) {
            $summary['payments_not_found']++;
            if (empty($payment['paid_at']) && strtolower((string) ($payment['status'] ?? '')) !== 'paid') {
                $update = $client->update('payments', 'id=eq.' . urlencode($paymentId), [
                    'status' => 'canceled',
                ]);
                if ($update['ok'] ?? false) {
                    $summary['payments_canceled_updated']++;
                }
            }
            continue;
        }

        $asaasStatus = (string) ($asaasData['status'] ?? '');
        if (asaas_status_is_paid($asaasStatus)) {
            if (empty($payment['paid_at']) || strtolower((string) ($payment['status'] ?? '')) !== 'paid') {
                $update = $client->update('payments', 'id=eq.' . urlencode($paymentId), [
                    'status' => 'paid',
                    'paid_at' => date('c'),
                ]);
                if ($update['ok'] ?? false) {
                    $summary['payments_paid_updated']++;
                }
            }
            continue;
        }

        if (asaas_status_is_canceled($asaasStatus)) {
            if (empty($payment['paid_at']) && strtolower((string) ($payment['status'] ?? '')) !== 'paid') {
                $update = $client->update('payments', 'id=eq.' . urlencode($paymentId), [
                    'status' => 'canceled',
                ]);
                if ($update['ok'] ?? false) {
                    $summary['payments_canceled_updated']++;
                }
            }
        }
    }

    $pendenciasResult = $client->select(
        'pendencia_de_cadastro',
        'select=id,paid_at,asaas_payment_id,asaas_invoice_url,guardian_cpf,guardian_email,payment_date'
        . '&limit=5000'
    );
    $pendencias = ($pendenciasResult['ok'] ?? false) && is_array($pendenciasResult['data'] ?? null)
        ? $pendenciasResult['data']
        : [];

    foreach ($pendencias as $pendencia) {
        $summary['pendencias_checked']++;
        if (!empty($pendencia['paid_at'])) {
            continue;
        }

        $pendenciaId = trim((string) ($pendencia['id'] ?? ''));
        $asaasId = trim((string) ($pendencia['asaas_payment_id'] ?? ''));
        $invoiceUrl = trim((string) ($pendencia['asaas_invoice_url'] ?? ''));
        $guardianCpf = normalize_digits((string) ($pendencia['guardian_cpf'] ?? ''));
        $guardianEmail = trim((string) ($pendencia['guardian_email'] ?? ''));
        $paymentDateRaw = trim((string) ($pendencia['payment_date'] ?? ''));
        $paymentDate = $paymentDateRaw !== '' ? date('Y-m-d', strtotime($paymentDateRaw)) : '';
        if ($pendenciaId === '') {
            continue;
        }

        $paymentsPool = [];
        if ($asaasId !== '') {
            $response = $asaas->getPayment($asaasId);
            if (($response['ok'] ?? false) && is_array($response['data'] ?? null)) {
                $paymentsPool[] = $response['data'];
            }
        }
        if ($invoiceUrl !== '') {
            $response = $asaas->findPaymentByInvoiceUrl($invoiceUrl);
            $list = ($response['ok'] ?? false) && is_array($response['data']['data'] ?? null)
                ? $response['data']['data']
                : [];
            $paymentsPool = array_merge($paymentsPool, $list);
        }

        $customerIds = [];
        if ($guardianCpf !== '') {
            $customersResponse = $asaas->findCustomersByCpfCnpj($guardianCpf);
            $customers = ($customersResponse['ok'] ?? false) && is_array($customersResponse['data']['data'] ?? null)
                ? $customersResponse['data']['data']
                : [];
            foreach ($customers as $customer) {
                $cid = trim((string) ($customer['id'] ?? ''));
                if ($cid !== '') {
                    $customerIds[$cid] = true;
                }
            }
        }
        if ($guardianEmail !== '') {
            $customersResponse = $asaas->findCustomersByEmail($guardianEmail);
            $customers = ($customersResponse['ok'] ?? false) && is_array($customersResponse['data']['data'] ?? null)
                ? $customersResponse['data']['data']
                : [];
            foreach ($customers as $customer) {
                $cid = trim((string) ($customer['id'] ?? ''));
                if ($cid !== '') {
                    $customerIds[$cid] = true;
                }
            }
        }

        foreach (array_keys($customerIds) as $customerId) {
            $paymentsResponse = $asaas->listPaymentsByCustomer($customerId, 100, 0);
            $list = ($paymentsResponse['ok'] ?? false) && is_array($paymentsResponse['data']['data'] ?? null)
                ? $paymentsResponse['data']['data']
                : [];
            $paymentsPool = array_merge($paymentsPool, $list);
        }

        $paymentsPool = unique_payments_by_id($paymentsPool);

        $openCandidates = [];
        $paidCandidates = [];
        foreach ($paymentsPool as $payment) {
            $status = (string) ($payment['status'] ?? '');
            if (asaas_status_is_open($status)) {
                if (
                    payment_matches_pendencia($payment, $asaasId, $invoiceUrl, $paymentDate)
                    || payment_date_matches($payment, $paymentDate)
                    || ($paymentDate === '' && ($asaasId !== '' || $invoiceUrl !== ''))
                ) {
                    $openCandidates[] = $payment;
                }
            }
            if (asaas_status_is_paid($status)) {
                if (
                    payment_matches_pendencia($payment, $asaasId, $invoiceUrl, $paymentDate)
                    || payment_date_matches($payment, $paymentDate)
                    || ($paymentDate === '' && ($guardianCpf !== '' || $guardianEmail !== ''))
                ) {
                    $paidCandidates[] = $payment;
                }
            }
        }

        $openPayment = pick_best_payment($openCandidates, $paymentDate, false);
        if ($openPayment !== null) {
            $update = $client->update('pendencia_de_cadastro', 'id=eq.' . urlencode($pendenciaId), [
                'asaas_payment_id' => $openPayment['id'] ?? null,
                'asaas_invoice_url' => $openPayment['invoiceUrl'] ?? ($openPayment['bankSlipUrl'] ?? null),
            ]);
            if ($update['ok'] ?? false) {
                // Mantém pendência aberta vinculada à cobrança em aberto/vencida.
                continue;
            }
        }

        $paidPayment = pick_best_payment($paidCandidates, $paymentDate, true);
        if ($paidPayment !== null) {
            $update = $client->update('pendencia_de_cadastro', 'id=eq.' . urlencode($pendenciaId), [
                'paid_at' => date('c'),
                'asaas_payment_id' => $paidPayment['id'] ?? $asaasId ?: null,
                'asaas_invoice_url' => $paidPayment['invoiceUrl'] ?? ($paidPayment['bankSlipUrl'] ?? ($invoiceUrl ?: null)),
            ]);
            if ($update['ok'] ?? false) {
                $summary['pendencias_paid_updated']++;
            }
            continue;
        }

        // Sem cobrança correspondente no Asaas (nem aberta/vencida, nem paga): remove da lista local de pendências.
        $delete = $client->delete('pendencia_de_cadastro', 'id=eq.' . urlencode($pendenciaId));
        if ($delete['ok'] ?? false) {
            $summary['pendencias_removed_no_charge']++;
        } else {
            // Fallback: ao menos desvincula se não conseguiu remover.
            $update = $client->update('pendencia_de_cadastro', 'id=eq.' . urlencode($pendenciaId), [
                'asaas_payment_id' => null,
                'asaas_invoice_url' => null,
            ]);
            if ($update['ok'] ?? false) {
                $summary['pendencias_unlinked']++;
            }
        }
        continue;
    }

    Helpers::json([
        'ok' => true,
        'summary' => $summary,
        'duplicate_dayuse' => [
            'count' => $summary['duplicate_dayuse_detected'],
            'removed' => $summary['pendencias_removed_duplicate_dayuse'],
            'remove_failed' => $summary['pendencias_remove_duplicate_failed'],
            'items' => $duplicateDayUseItems,
        ],
    ]);
} catch (\Throwable $e) {
    $logPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'error_log_custom.txt';
    @file_put_contents($logPath, '[admin-sync-charges-payments] ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    Helpers::json([
        'ok' => false,
        'error' => 'Falha interna na sincronização.',
        'details' => $e->getMessage(),
    ], 500);
}
