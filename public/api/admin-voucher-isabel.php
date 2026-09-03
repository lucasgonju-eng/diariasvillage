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
use App\MonthlyStudents;
use App\SupabaseClient;

const ISABEL_VOUCHER_STUDENT_NAME = 'Isabel Gonçalves Rauen Espinola';
const ISABEL_VOUCHER_LIMIT = 30;

function voucher_normalize_text(string $value): string
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
    return trim(preg_replace('/[^A-Z0-9]+/', '', $value) ?? '');
}

function voucher_student_matches(string $name): bool
{
    $target = voucher_normalize_text(ISABEL_VOUCHER_STUDENT_NAME);
    $actual = voucher_normalize_text($name);
    return $actual !== '' && ($actual === $target || strpos($actual, 'ISABELGONCALVESRAUEN') === 0);
}

function voucher_parse_date(string $raw): ?string
{
    return MonthlyStudents::parseFlexibleDate($raw);
}

function voucher_extract_dates(array $payment): array
{
    $dates = MonthlyStudents::extractDatesFromPayment(
        (string) ($payment['daily_type'] ?? ''),
        (string) ($payment['payment_date'] ?? '')
    );
    $dates = array_values(array_unique(array_filter($dates)));
    sort($dates);
    return $dates;
}

function voucher_format_date_br(string $isoDate): string
{
    $time = strtotime($isoDate);
    if ($time === false) {
        return $isoDate;
    }
    return date('d/m/Y', $time);
}

function voucher_label_from_billing_type(string $billingType): string
{
    if (preg_match('/VOUCHER_ISABEL_(\d{1,2})_30/', $billingType, $match)) {
        return 'Voucher ' . (int) $match[1] . '/30';
    }
    return '';
}

Helpers::requireAdminRole(\App\AdminAuth::ROLE_ADMIN);

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = [];
}

$paymentId = trim((string) ($payload['payment_id'] ?? ($payload['id'] ?? '')));
if ($paymentId === '') {
    Helpers::json(['ok' => false, 'error' => 'ID da cobrança inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$paymentResult = $client->select(
    'payments',
    'select=*,students(name,enrollment),guardians(parent_name,email)&id=eq.' . urlencode($paymentId) . '&limit=1'
);
$payment = (($paymentResult['ok'] ?? false) && !empty($paymentResult['data'][0])) ? $paymentResult['data'][0] : null;
if (!is_array($payment)) {
    Helpers::json(['ok' => false, 'error' => 'Cobrança não encontrada.'], 404);
}

$student = is_array($payment['students'] ?? null) ? $payment['students'] : [];
$studentName = trim((string) ($student['name'] ?? ''));
if (!voucher_student_matches($studentName)) {
    Helpers::json(['ok' => false, 'error' => 'Voucher disponível apenas para Isabel Gonçalves Rauen.'], 422);
}

$status = strtolower(trim((string) ($payment['status'] ?? '')));
if ($status === 'paid' || !empty($payment['paid_at'])) {
    $existingLabel = voucher_label_from_billing_type((string) ($payment['billing_type'] ?? ''));
    Helpers::json([
        'ok' => true,
        'message' => $existingLabel !== '' ? 'Cobrança já liquidada como ' . $existingLabel . '.' : 'Cobrança já está paga.',
        'voucher_label' => $existingLabel,
    ]);
}

$targetDates = voucher_extract_dates($payment);
if (empty($targetDates)) {
    Helpers::json(['ok' => false, 'error' => 'Não foi possível identificar a data do day-use desta cobrança.'], 422);
}
$targetFirstDate = $targetDates[0];

$studentId = trim((string) ($payment['student_id'] ?? ''));
$allPaymentsQuery = 'select=id,student_id,payment_date,daily_type,billing_type,status,paid_at,created_at,students(name)'
    . '&order=payment_date.asc&limit=5000';
if ($studentId !== '') {
    $allPaymentsQuery .= '&student_id=eq.' . urlencode($studentId);
}
$allPaymentsResult = $client->select('payments', $allPaymentsQuery);
$allPayments = (($allPaymentsResult['ok'] ?? false) && is_array($allPaymentsResult['data'] ?? null))
    ? $allPaymentsResult['data']
    : [];

$voucherDates = [];
foreach ($allPayments as $row) {
    if (!is_array($row)) {
        continue;
    }
    $rowStudent = is_array($row['students'] ?? null) ? $row['students'] : [];
    $rowStudentName = trim((string) ($rowStudent['name'] ?? $studentName));
    if (!voucher_student_matches($rowStudentName)) {
        continue;
    }
    foreach (voucher_extract_dates($row) as $isoDate) {
        $voucherDates[$isoDate] = true;
    }
}
foreach ($targetDates as $isoDate) {
    $voucherDates[$isoDate] = true;
}
$orderedDates = array_keys($voucherDates);
sort($orderedDates);
$voucherNumberByDate = [];
$position = 1;
foreach ($orderedDates as $isoDate) {
    $voucherNumberByDate[$isoDate] = $position;
    $position++;
}

$voucherNumber = (int) ($voucherNumberByDate[$targetFirstDate] ?? 0);
if ($voucherNumber < 1) {
    Helpers::json(['ok' => false, 'error' => 'Não foi possível calcular a ordem do voucher.'], 500);
}
if ($voucherNumber > ISABEL_VOUCHER_LIMIT) {
    Helpers::json([
        'ok' => false,
        'error' => 'Limite de ' . ISABEL_VOUCHER_LIMIT . ' vouchers excedido. Esta data seria Voucher ' . $voucherNumber . '/30.',
    ], 422);
}

$asaasPaymentId = trim((string) ($payment['asaas_payment_id'] ?? ''));
$asaasWarning = '';
if ($asaasPaymentId !== '') {
    $asaas = new AsaasClient(new HttpClient());
    $deleteAsaas = $asaas->deletePayment($asaasPaymentId);
    if (!($deleteAsaas['ok'] ?? false)) {
        $asaasWarning = 'Não foi possível cancelar automaticamente a cobrança no Asaas. Confira manualmente.';
    }
}

$voucherLabel = 'Voucher ' . $voucherNumber . '/30';
$voucherBillingType = 'VOUCHER_ISABEL_' . str_pad((string) $voucherNumber, 2, '0', STR_PAD_LEFT) . '_30';
$dateLabel = implode(', ', array_map('voucher_format_date_br', $targetDates));
$dailyTypeParts = explode('|', (string) ($payment['daily_type'] ?? 'emergencial'), 2);
$dailyBaseType = strtolower(trim((string) ($dailyTypeParts[0] ?? 'emergencial')));
if (!in_array($dailyBaseType, ['planejada', 'emergencial'], true)) {
    $dailyBaseType = 'emergencial';
}

$update = $client->update('payments', 'id=eq.' . urlencode($paymentId), [
    'amount' => 0,
    'status' => 'paid',
    'billing_type' => $voucherBillingType,
    'asaas_payment_id' => null,
    'paid_at' => date('c'),
    'daily_type' => $dailyBaseType . '|' . $dateLabel,
]);
if (!($update['ok'] ?? false)) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao liquidar cobrança com voucher.'], 500);
}

Helpers::json([
    'ok' => true,
    'message' => 'Cobrança liquidada como ' . $voucherLabel . '.',
    'voucher_label' => $voucherLabel,
    'voucher_number' => $voucherNumber,
    'voucher_limit' => ISABEL_VOUCHER_LIMIT,
    'asaas_warning' => $asaasWarning !== '' ? $asaasWarning : null,
]);
