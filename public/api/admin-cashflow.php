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
use App\AsaasClient;
use App\HttpClient;
use App\MonthlyStudents;
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

function resolve_base_amount(string $typeLabel, float $storedAmount): float
{
    if ($storedAmount > 97.01) {
        return $storedAmount;
    }
    if ($typeLabel === 'Emergencial') {
        return 97.00;
    }
    return 77.00;
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

function fetch_asaas_extrato_summary(string $from, string $to): array
{
    $asaas = new AsaasClient(new HttpClient());
    $warnings = [];
    $credits = 0.0;
    $debits = 0.0;
    $feeDebit = 0.0;
    $count = 0;
    $offset = 0;
    $maxPages = 60;

    for ($page = 0; $page < $maxPages; $page++) {
        $res = $asaas->listFinancialTransactions($from, $to, 100, $offset, 'asc');
        if (!($res['ok'] ?? false)) {
            $warnings[] = 'Falha ao consultar extrato financeiro no Asaas.';
            break;
        }
        $list = is_array($res['data']['data'] ?? null) ? $res['data']['data'] : [];
        if (empty($list)) {
            break;
        }

        foreach ($list as $tx) {
            if (!is_array($tx)) {
                continue;
            }
            $count++;
            $value = (float) ($tx['value'] ?? 0);
            if ($value >= 0) {
                $credits += $value;
            } else {
                $debits += $value;
            }
            $type = strtoupper(trim((string) ($tx['type'] ?? '')));
            if ($value < 0 && (str_contains($type, 'FEE') || str_contains($type, 'PROMOTIONAL_CODE'))) {
                $feeDebit += abs($value);
            }
        }

        if (count($list) < 100) {
            break;
        }
        $offset += 100;
    }

    $balanceAvailable = null;
    $balanceRes = $asaas->getFinanceBalance();
    if ($balanceRes['ok'] ?? false) {
        $balanceAvailable = isset($balanceRes['data']['balance']) ? (float) $balanceRes['data']['balance'] : null;
    } else {
        $warnings[] = 'Não foi possível consultar saldo disponível no Asaas.';
    }

    return [
        'count' => $count,
        'credits' => round($credits, 2),
        'debits' => round($debits, 2),
        'net' => round($credits + $debits, 2),
        'fees' => round($feeDebit, 2),
        'balance_available' => $balanceAvailable !== null ? round((float) $balanceAvailable, 2) : null,
        'warnings' => $warnings,
    ];
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
$monthlyMode = strtolower(trim((string) ($_GET['monthly_mode'] ?? 'subtract')));
if (!in_array($monthlyMode, ['subtract', 'show'], true)) {
    $monthlyMode = 'subtract';
}
$excludeStudentTerms = parse_filter_terms((string) ($_GET['exclude_student'] ?? ''));
$excludeGenericTerms = parse_filter_terms((string) ($_GET['exclude_term'] ?? ''));
$transitionCutoff = '2026-03-16';

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
$monthlyAdjustment = [
    'mode' => $monthlyMode,
    'count' => 0,
    'amount' => 0.0,
    'description' => 'Aluno mensalista',
];

foreach ($rows as $row) {
    $student = $studentsById[(string) ($row['student_id'] ?? '')] ?? [];
    $studentName = trim((string) ($student['name'] ?? '-'));
    $enrollment = trim((string) ($student['enrollment'] ?? '-'));
    $status = trim((string) ($row['status'] ?? '-'));
    $dailyTypeRaw = trim((string) ($row['daily_type'] ?? ''));
    $dailyTypeLabel = normalize_day_use_type($dailyTypeRaw);
    $storedAmount = (float) ($row['amount'] ?? 0);
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
    $baseAmount = resolve_base_amount($dailyTypeLabel, $storedAmount);
    $effectiveAmount = ($dateKey <= $transitionCutoff && $baseAmount <= 97.01) ? 77.00 : $baseAmount;

    $items[] = [
        'id' => $row['id'] ?? null,
        'source' => 'payments',
        'student_id' => (string) ($row['student_id'] ?? ''),
        'student_name' => $studentName !== '' ? $studentName : '-',
        'date' => $dateKey,
        'day_use_type' => $dailyTypeLabel,
        'enrollment' => $enrollment !== '' ? $enrollment : '-',
        'amount' => $effectiveAmount,
        'base_amount' => $baseAmount,
        'status' => $status !== '' ? $status : '-',
        'billing_type' => $billingType !== '' ? $billingType : '-',
        'paid_at' => $paidAt,
        'created_at' => $createdAt,
    ];
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
        'source' => 'pendencia',
        'student_id' => '',
        'student_name' => $studentName !== '' ? $studentName : '-',
        'date' => $dateKey,
        'day_use_type' => $dayUseType,
        'enrollment' => $enrollment !== '' ? $enrollment : '-',
        'amount' => $amount,
        'status' => $status,
        'billing_type' => $billingType,
        'paid_at' => $paidAt,
        'created_at' => $createdAt,
    ];
}

if ($monthlyMode === 'subtract') {
    $monthlyItems = MonthlyStudents::load();
    $monthlyById = MonthlyStudents::mapByStudentId($monthlyItems);
    $monthlyByName = MonthlyStudents::mapByNormalizedName($monthlyItems);
    if (!empty($monthlyById) || !empty($monthlyByName)) {
        $paymentItems = array_values(array_filter($items, static fn($item): bool => ($item['source'] ?? '') === 'payments'));
        $otherItems = array_values(array_filter($items, static fn($item): bool => ($item['source'] ?? '') !== 'payments'));
        $classified = MonthlyStudents::classifyRowsByQuota(
            $paymentItems,
            static function (array $item): array {
                return [
                    'student_id' => (string) ($item['student_id'] ?? ''),
                    'student_name' => (string) ($item['student_name'] ?? ''),
                    'dates' => [(string) ($item['date'] ?? '')],
                    'created_at' => (string) ($item['created_at'] ?? ''),
                ];
            },
            $monthlyById,
            $monthlyByName
        );
        $covered = $classified['covered'] ?? [];
        foreach ($covered as $itemCovered) {
            $monthlyAdjustment['count']++;
            $monthlyAdjustment['amount'] += (float) ($itemCovered['amount'] ?? 0);
        }
        $items = array_merge($classified['visible'] ?? [], $otherItems);
    }
}

usort($items, static function (array $a, array $b): int {
    $aName = normalize_lower((string) ($a['student_name'] ?? ''));
    $bName = normalize_lower((string) ($b['student_name'] ?? ''));
    $cmpName = strcmp($aName, $bName);
    if ($cmpName !== 0) {
        return $cmpName;
    }
    return strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
});

$totalAmount = 0.0;
$totalPaidAmount = 0.0;
$paidInterManual = 0.0;
$paidBoleto = 0.0;
$paidAsaasLocal = 0.0;
foreach ($items as $item) {
    $value = (float) ($item['amount'] ?? 0);
    $totalAmount += $value;
    if (strtolower((string) ($item['status'] ?? '')) === 'paid') {
        $totalPaidAmount += $value;
        $billingType = strtoupper(trim((string) ($item['billing_type'] ?? '')));
        if ($billingType === 'PIX_MANUAL') {
            $paidInterManual += $value;
        } elseif ($billingType === 'BOLETO') {
            $paidBoleto += $value;
        } else {
            // Classificação local por billing_type, sem considerar débitos/taxas do extrato.
            $paidAsaasLocal += $value;
        }
    }
}

$asaasExtrato = fetch_asaas_extrato_summary($from, $to);
$warnings = array_merge($warnings, $asaasExtrato['warnings'] ?? []);

Helpers::json([
    'ok' => true,
    'period' => ['from' => $from, 'to' => $to],
    'totals' => [
        'count' => count($items),
        'amount' => round($totalAmount, 2),
        'paid_amount' => round($totalPaidAmount, 2),
        'paid_by_account' => [
            'inter_pix_manual' => round($paidInterManual, 2),
            'boleto' => round($paidBoleto, 2),
            'asaas_local' => round($paidAsaasLocal, 2),
            'asaas' => (float) ($asaasExtrato['net'] ?? 0.0),
            'asaas_extrato_credit' => (float) ($asaasExtrato['credits'] ?? 0.0),
            'asaas_extrato_debit' => (float) ($asaasExtrato['debits'] ?? 0.0),
            'asaas_extrato_fees' => (float) ($asaasExtrato['fees'] ?? 0.0),
            'asaas_balance_available' => $asaasExtrato['balance_available'] ?? null,
        ],
    ],
    'monthly_adjustment' => [
        'mode' => $monthlyAdjustment['mode'],
        'count' => (int) $monthlyAdjustment['count'],
        'amount' => round((float) $monthlyAdjustment['amount'], 2),
        'description' => (string) $monthlyAdjustment['description'],
    ],
    'warnings' => $warnings,
    'items' => $items,
]);
