<?php
require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

function normalize_date(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return null;
    }
    $time = strtotime($raw . ' 00:00:00');
    if ($time === false) {
        return null;
    }
    return date('Y-m-d', $time);
}

function normalize_day_use_type(string $dailyType): string
{
    $base = strtolower(trim(explode('|', $dailyType, 2)[0] ?? ''));
    if ($base === 'emergencial') {
        return 'Emergencial';
    }
    if ($base === 'planejada') {
        return 'Planejada';
    }
    return $base !== '' ? ucfirst($base) : '-';
}

$from = normalize_date($_GET['from'] ?? '') ?? date('Y') . '-01-05';
$to = normalize_date($_GET['to'] ?? '') ?? date('Y-m-d');

if ($from > $to) {
    Helpers::json(['ok' => false, 'error' => 'Período inválido: data inicial maior que a final.'], 422);
}

$statusFilter = strtolower(trim((string) ($_GET['status'] ?? '')));
$dayUseTypeFilter = strtolower(trim((string) ($_GET['day_use_type'] ?? '')));
$studentFilter = mb_strtolower(trim((string) ($_GET['student_name'] ?? '')), 'UTF-8');
$enrollmentFilter = mb_strtolower(trim((string) ($_GET['enrollment'] ?? '')), 'UTF-8');
$billingTypeFilter = strtoupper(trim((string) ($_GET['billing_type'] ?? '')));

$client = new SupabaseClient(new HttpClient());
$query = http_build_query([
    'select' => 'id,amount,status,billing_type,daily_type,payment_date,created_at,paid_at,students(name,enrollment)',
    'payment_date' => 'gte.' . $from,
    'payment_date_2' => 'lte.' . $to,
    'order' => 'payment_date.desc',
    'limit' => 5000,
], '', '&', PHP_QUERY_RFC3986);

// O PostgREST não aceita chave repetida com http_build_query sem [].
// Ajuste manual para manter "payment_date" nos dois filtros.
$query = str_replace('payment_date_2=', 'payment_date=', $query);

$result = $client->select('payments', $query);
if (!$result['ok']) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao carregar fluxo de caixa.'], 500);
}

$rows = $result['data'] ?? [];
$items = [];
$totalAmount = 0.0;
$totalPaidAmount = 0.0;

foreach ($rows as $row) {
    $student = $row['students'] ?? [];
    $studentName = trim((string) ($student['name'] ?? '-'));
    $enrollment = trim((string) ($student['enrollment'] ?? '-'));
    $status = trim((string) ($row['status'] ?? '-'));
    $dailyTypeRaw = trim((string) ($row['daily_type'] ?? ''));
    $dailyTypeLabel = normalize_day_use_type($dailyTypeRaw);
    $amount = (float) ($row['amount'] ?? 0);
    $billingType = trim((string) ($row['billing_type'] ?? '-'));

    if ($statusFilter !== '' && strtolower($status) !== $statusFilter) {
        continue;
    }
    if ($dayUseTypeFilter !== '') {
        $baseType = strtolower(trim(explode('|', $dailyTypeRaw, 2)[0] ?? ''));
        if ($baseType !== $dayUseTypeFilter) {
            continue;
        }
    }
    if ($billingTypeFilter !== '' && strtoupper($billingType) !== $billingTypeFilter) {
        continue;
    }
    if ($studentFilter !== '' && !str_contains(mb_strtolower($studentName, 'UTF-8'), $studentFilter)) {
        continue;
    }
    if ($enrollmentFilter !== '' && !str_contains(mb_strtolower($enrollment, 'UTF-8'), $enrollmentFilter)) {
        continue;
    }

    $paymentDate = (string) ($row['payment_date'] ?? '');
    $paidAt = (string) ($row['paid_at'] ?? '');
    $createdAt = (string) ($row['created_at'] ?? '');
    $displayDate = $paymentDate !== '' ? $paymentDate : ($paidAt !== '' ? $paidAt : $createdAt);

    $items[] = [
        'id' => $row['id'] ?? null,
        'student_name' => $studentName !== '' ? $studentName : '-',
        'date' => $displayDate,
        'day_use_type' => $dailyTypeLabel,
        'enrollment' => $enrollment !== '' ? $enrollment : '-',
        'amount' => $amount,
        'status' => $status !== '' ? $status : '-',
        'billing_type' => $billingType !== '' ? $billingType : '-',
        'paid_at' => $paidAt,
    ];

    $totalAmount += $amount;
    if (strtolower($status) === 'paid') {
        $totalPaidAmount += $amount;
    }
}

Helpers::json([
    'ok' => true,
    'period' => ['from' => $from, 'to' => $to],
    'totals' => [
        'count' => count($items),
        'amount' => round($totalAmount, 2),
        'paid_amount' => round($totalPaidAmount, 2),
    ],
    'items' => $items,
]);
