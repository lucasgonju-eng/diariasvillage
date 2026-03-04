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

function to_date_key(?string $value): string
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

function normalize_lower(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (function_exists('mb_strtolower')) {
        try {
            return mb_strtolower($value, 'UTF-8');
        } catch (\Throwable $e) {
            // Fallback para entradas com encoding inválido.
        }
    }
    return strtolower($value);
}

function parse_filter_terms(string $raw): array
{
    $normalized = str_replace(["\r\n", "\n", "\r", ';', '+'], ',', $raw);
    $parts = array_map('trim', explode(',', $normalized));
    $terms = [];
    foreach ($parts as $part) {
        $value = normalize_lower($part);
        if ($value !== '') {
            $terms[] = $value;
        }
    }
    return array_values(array_unique($terms));
}

function matches_any_term(string $haystack, array $terms): bool
{
    if (empty($terms)) {
        return false;
    }
    $text = normalize_lower($haystack);
    foreach ($terms as $term) {
        if ($term !== '' && str_contains($text, $term)) {
            return true;
        }
    }
    return false;
}

$from = normalize_date($_GET['from'] ?? '') ?? date('Y') . '-01-05';
$to = normalize_date($_GET['to'] ?? '') ?? date('Y-m-d');

if ($from > $to) {
    Helpers::json(['ok' => false, 'error' => 'Período inválido: data inicial maior que a final.'], 422);
}

$statusFilter = strtolower(trim((string) ($_GET['status'] ?? '')));
$dayUseTypeFilter = strtolower(trim((string) ($_GET['day_use_type'] ?? '')));
$studentFilter = normalize_lower((string) ($_GET['student_name'] ?? ''));
$enrollmentFilter = normalize_lower((string) ($_GET['enrollment'] ?? ''));
$billingTypeFilter = strtoupper(trim((string) ($_GET['billing_type'] ?? '')));
$excludeStudentTerms = parse_filter_terms((string) ($_GET['exclude_student'] ?? ''));
$excludeGenericTerms = parse_filter_terms((string) ($_GET['exclude_term'] ?? ''));

$client = new SupabaseClient(new HttpClient());
$paymentsResult = $client->select(
    'payments',
    'select=id,student_id,amount,status,billing_type,daily_type,payment_date,created_at,paid_at&order=created_at.desc&limit=10000'
);

// Algumas bases não têm coluna enrollment em pendencia_de_cadastro.
$pendenciaResult = $client->select(
    'pendencia_de_cadastro',
    'select=id,student_name,enrollment,payment_date,created_at,paid_at&order=created_at.desc&limit=5000'
);
if (!($pendenciaResult['ok'] ?? false)) {
    $pendenciaResult = $client->select(
        'pendencia_de_cadastro',
        'select=id,student_name,payment_date,created_at,paid_at&order=created_at.desc&limit=5000'
    );
}

if (!($paymentsResult['ok'] ?? false) && !($pendenciaResult['ok'] ?? false)) {
    $paymentsErr = is_string($paymentsResult['error'] ?? null) ? $paymentsResult['error'] : '';
    $pendenciasErr = is_string($pendenciaResult['error'] ?? null) ? $pendenciaResult['error'] : '';
    Helpers::json([
        'ok' => false,
        'error' => 'Falha ao carregar fluxo de caixa.',
        'details' => trim($paymentsErr . ' ' . $pendenciasErr) ?: null,
    ], 500);
}

$rows = ($paymentsResult['ok'] ?? false) ? ($paymentsResult['data'] ?? []) : [];
$pendencias = ($pendenciaResult['ok'] ?? false) ? ($pendenciaResult['data'] ?? []) : [];
$warnings = [];
if (!($paymentsResult['ok'] ?? false)) {
    $warnings[] = 'Não foi possível carregar a tabela payments.';
}
if (!($pendenciaResult['ok'] ?? false)) {
    $warnings[] = 'Não foi possível carregar a tabela pendencia_de_cadastro.';
}

$studentsById = [];
if (!empty($rows)) {
    $studentIds = [];
    foreach ($rows as $row) {
        $sid = trim((string) ($row['student_id'] ?? ''));
        if ($sid !== '') {
            $studentIds[$sid] = true;
        }
    }
    $ids = array_keys($studentIds);
    if (!empty($ids)) {
        $quoted = array_map(static fn($id) => '"' . str_replace('"', '', $id) . '"', $ids);
        $studentsResult = $client->select(
            'students',
            'select=id,name,enrollment&id=in.(' . implode(',', $quoted) . ')&limit=10000'
        );
        if (($studentsResult['ok'] ?? false) && !empty($studentsResult['data'])) {
            foreach ($studentsResult['data'] as $st) {
                $sid = (string) ($st['id'] ?? '');
                if ($sid !== '') {
                    $studentsById[$sid] = $st;
                }
            }
        } else {
            $warnings[] = 'Não foi possível carregar nomes/matrículas da tabela students.';
        }
    }
}
$items = [];
$totalAmount = 0.0;
$totalPaidAmount = 0.0;

foreach ($rows as $row) {
    $student = $studentsById[(string) ($row['student_id'] ?? '')] ?? [];
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
    if ($studentFilter !== '' && !str_contains(normalize_lower($studentName), $studentFilter)) {
        continue;
    }
    if ($enrollmentFilter !== '' && !str_contains(normalize_lower($enrollment), $enrollmentFilter)) {
        continue;
    }
    if (matches_any_term($studentName, $excludeStudentTerms)) {
        continue;
    }
    $haystack = $studentName . ' ' . $enrollment . ' ' . $dailyTypeLabel . ' ' . $status . ' ' . $billingType;
    if (matches_any_term($haystack, $excludeGenericTerms)) {
        continue;
    }

    $paymentDate = (string) ($row['payment_date'] ?? '');
    $paidAt = (string) ($row['paid_at'] ?? '');
    $createdAt = (string) ($row['created_at'] ?? '');
    $dateKey = to_date_key($paymentDate !== '' ? $paymentDate : ($paidAt !== '' ? $paidAt : $createdAt));
    if ($dateKey === '' || $dateKey < $from || $dateKey > $to) {
        continue;
    }

    $items[] = [
        'id' => $row['id'] ?? null,
        'student_name' => $studentName !== '' ? $studentName : '-',
        'date' => $dateKey,
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

foreach ($pendencias as $p) {
    $studentName = trim((string) ($p['student_name'] ?? '-'));
    $enrollment = trim((string) ($p['enrollment'] ?? '-'));
    $paidAt = (string) ($p['paid_at'] ?? '');
    $paymentDate = (string) ($p['payment_date'] ?? '');
    $createdAt = (string) ($p['created_at'] ?? '');
    $dateKey = to_date_key($paymentDate !== '' ? $paymentDate : ($paidAt !== '' ? $paidAt : $createdAt));
    if ($dateKey === '' || $dateKey < $from || $dateKey > $to) {
        continue;
    }

    $status = $paidAt !== '' ? 'paid' : 'pending';
    $billingType = 'PIX';
    $dayUseType = 'Pendência cadastro';
    $amount = 77.00;

    if ($statusFilter !== '' && $status !== $statusFilter) {
        continue;
    }
    if ($billingTypeFilter !== '' && strtoupper($billingType) !== $billingTypeFilter) {
        continue;
    }
    if ($dayUseTypeFilter !== '') {
        // Pendência não é planejada/emergencial.
        continue;
    }
    if ($studentFilter !== '' && !str_contains(normalize_lower($studentName), $studentFilter)) {
        continue;
    }
    if ($enrollmentFilter !== '' && !str_contains(normalize_lower($enrollment), $enrollmentFilter)) {
        continue;
    }
    if (matches_any_term($studentName, $excludeStudentTerms)) {
        continue;
    }
    $haystack = $studentName . ' ' . $enrollment . ' ' . $dayUseType . ' ' . $status . ' ' . $billingType;
    if (matches_any_term($haystack, $excludeGenericTerms)) {
        continue;
    }

    $items[] = [
        'id' => $p['id'] ?? null,
        'student_name' => $studentName !== '' ? $studentName : '-',
        'date' => $dateKey,
        'day_use_type' => $dayUseType,
        'enrollment' => $enrollment !== '' ? $enrollment : '-',
        'amount' => $amount,
        'status' => $status,
        'billing_type' => $billingType,
        'paid_at' => $paidAt,
    ];

    $totalAmount += $amount;
    if ($status === 'paid') {
        $totalPaidAmount += $amount;
    }
}

usort($items, static function (array $a, array $b): int {
    return strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
});

Helpers::json([
    'ok' => true,
    'period' => ['from' => $from, 'to' => $to],
    'totals' => [
        'count' => count($items),
        'amount' => round($totalAmount, 2),
        'paid_amount' => round($totalPaidAmount, 2),
    ],
    'warnings' => $warnings,
    'items' => $items,
]);
