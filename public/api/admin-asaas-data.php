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

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    Helpers::json(['ok' => false, 'error' => 'Método inválido.'], 405);
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

function normalize_asaas_transaction(array $tx): array
{
    $value = (float) ($tx['value'] ?? 0);
    $type = trim((string) ($tx['type'] ?? ''));
    $description = trim((string) ($tx['description'] ?? ''));
    $date = trim((string) ($tx['date'] ?? ''));
    $paymentId = trim((string) ($tx['paymentId'] ?? ''));
    $balance = isset($tx['balance']) ? (float) $tx['balance'] : null;

    $typeUpper = strtoupper($type);
    $feeValue = ($value < 0 && (str_contains($typeUpper, 'FEE') || str_contains($typeUpper, 'PROMOTIONAL_CODE')))
        ? abs($value)
        : 0.0;

    return [
        'id' => trim((string) ($tx['id'] ?? '')),
        'status' => $value >= 0 ? 'CREDITO' : 'DEBITO',
        'type' => $type,
        'customer_id' => '',
        'customer_name' => '',
        'description' => $description !== '' ? $description : $type,
        'billing_type' => '-',
        'value' => $value,
        'net_value' => $value,
        'fee_value' => $feeValue,
        'due_date' => $date,
        'paid_at' => $date,
        'invoice_url' => '',
        'external_reference' => $paymentId,
        'date_created' => $date,
        'date' => $date,
        'balance' => $balance,
        'payment_id' => $paymentId,
    ];
}

function is_realizacao_inter_ci(array $item): bool
{
    $value = (float) ($item['value'] ?? 0);
    if ($value >= 0) {
        return false;
    }

    $type = strtoupper(trim((string) ($item['type'] ?? '')));
    $description = strtoupper(trim((string) ($item['description'] ?? '')));

    if (str_contains($type, 'FEE') || str_contains($type, 'REVERSAL')) {
        return false;
    }

    // Regra principal: transação Pix para a conta Inter CI (empresa).
    if (str_contains($description, 'CENTRO INTEGRADO DE EDUCACAO LIVRE E EVENTOS LTDA')) {
        return true;
    }

    // Fallback para transferências operacionais sem taxa/reversão.
    if (in_array($type, ['PIX_TRANSACTION_DEBIT', 'TRANSFER', 'INTERNAL_TRANSFER_DEBIT'], true)) {
        return true;
    }

    return false;
}

function pick_payment_date(array $payment): string
{
    foreach (['clientPaymentDate', 'paymentDate', 'confirmedDate', 'dueDate', 'dateCreated'] as $key) {
        $value = trim((string) ($payment[$key] ?? ''));
        if ($value !== '') {
            $ts = strtotime($value);
            if ($ts !== false) {
                return date('Y-m-d', $ts);
            }
        }
    }
    return '';
}

function build_customer_student_map(SupabaseClient $client, array &$warnings): array
{
    $map = [];
    $res = $client->select(
        'guardians',
        'select=asaas_customer_id,students(name)&asaas_customer_id=not.is.null&limit=10000'
    );
    if (!($res['ok'] ?? false)) {
        $warnings[] = 'Não foi possível mapear clientes Asaas para alunos.';
        return $map;
    }
    $rows = is_array($res['data'] ?? null) ? $res['data'] : [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $customerId = trim((string) ($row['asaas_customer_id'] ?? ''));
        if ($customerId === '') {
            continue;
        }
        $studentRaw = $row['students'] ?? null;
        $studentName = '';
        if (is_array($studentRaw)) {
            $studentName = trim((string) ($studentRaw['name'] ?? ''));
        }
        if ($studentName === '') {
            continue;
        }
        if (!isset($map[$customerId])) {
            $map[$customerId] = [];
        }
        $map[$customerId][$studentName] = true;
    }

    $labels = [];
    foreach ($map as $customerId => $namesSet) {
        $names = array_keys($namesSet);
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        $labels[$customerId] = implode(' | ', $names);
    }
    return $labels;
}

function fetch_asaas_payments_by_statuses(
    AsaasClient $asaas,
    array $customerStudentMap,
    array $statuses,
    int $maxPages,
    array &$warnings
): array {
    $items = [];
    foreach ($statuses as $status) {
        $offset = 0;
        for ($page = 0; $page < $maxPages; $page++) {
            $res = $asaas->listPaymentsByStatus((string) $status, 100, $offset);
            if (!($res['ok'] ?? false)) {
                $warnings[] = 'Falha ao consultar pagamentos no Asaas (status ' . $status . ').';
                break;
            }
            $list = is_array($res['data']['data'] ?? null) ? $res['data']['data'] : [];
            if (empty($list)) {
                break;
            }
            foreach ($list as $payment) {
                if (!is_array($payment)) {
                    continue;
                }
                $customerId = trim((string) ($payment['customer'] ?? ''));
                $studentName = trim((string) ($customerStudentMap[$customerId] ?? ''));
                $customerName = trim((string) ($payment['customerName'] ?? ''));
                $customerLabel = $studentName !== '' ? $studentName : ($customerName !== '' ? $customerName : $customerId);
                $items[] = [
                    'id' => trim((string) ($payment['id'] ?? '')),
                    'status' => strtoupper(trim((string) ($payment['status'] ?? ''))),
                    'student_name' => $studentName,
                    'customer_label' => $customerLabel,
                    'customer_name' => $customerName,
                    'customer_id' => $customerId,
                    'value' => (float) ($payment['value'] ?? 0),
                    'net_value' => (float) ($payment['netValue'] ?? 0),
                    'billing_type' => trim((string) ($payment['billingType'] ?? '')),
                    'date' => pick_payment_date($payment),
                ];
            }
            if (count($list) < 100) {
                break;
            }
            $offset += 100;
        }
    }
    return $items;
}

function build_analytics(array $extrato, array $paidPayments, array $openPayments): array
{
    $daily = [];
    foreach (($extrato['items'] ?? []) as $item) {
        $date = trim((string) ($item['date'] ?? ''));
        if ($date === '') {
            continue;
        }
        if (!isset($daily[$date])) {
            $daily[$date] = ['date' => $date, 'credits' => 0.0, 'debits' => 0.0, 'net' => 0.0];
        }
        $value = (float) ($item['value'] ?? 0);
        if ($value >= 0) {
            $daily[$date]['credits'] += $value;
        } else {
            $daily[$date]['debits'] += $value;
        }
        $daily[$date]['net'] += $value;
    }
    ksort($daily);
    $dailySeries = array_values(array_map(static function (array $row): array {
        $row['credits'] = round((float) $row['credits'], 2);
        $row['debits'] = round((float) $row['debits'], 2);
        $row['net'] = round((float) $row['net'], 2);
        return $row;
    }, $daily));

    $good = [];
    foreach ($paidPayments as $p) {
        $name = trim((string) ($p['customer_label'] ?? ''));
        $id = trim((string) ($p['customer_id'] ?? ''));
        $key = $name !== '' ? $name : ($id !== '' ? $id : 'Sem identificação');
        if (!isset($good[$key])) {
            $good[$key] = ['customer' => $key, 'paid_count' => 0, 'paid_total' => 0.0, 'open_count' => 0, 'open_total' => 0.0];
        }
        $good[$key]['paid_count']++;
        $good[$key]['paid_total'] += (float) ($p['value'] ?? 0);
    }

    $bad = [];
    foreach ($openPayments as $p) {
        $name = trim((string) ($p['customer_label'] ?? ''));
        $id = trim((string) ($p['customer_id'] ?? ''));
        $key = $name !== '' ? $name : ($id !== '' ? $id : 'Sem identificação');
        if (!isset($bad[$key])) {
            $bad[$key] = ['customer' => $key, 'open_count' => 0, 'open_total' => 0.0];
        }
        $bad[$key]['open_count']++;
        $bad[$key]['open_total'] += (float) ($p['value'] ?? 0);
    }

    $topAdimplentes = array_values($good);
    usort($topAdimplentes, static function (array $a, array $b): int {
        $cmp = $b['paid_total'] <=> $a['paid_total'];
        return $cmp !== 0 ? $cmp : ($b['paid_count'] <=> $a['paid_count']);
    });
    $topAdimplentes = array_slice(array_map(static function (array $row): array {
        $row['paid_total'] = round((float) $row['paid_total'], 2);
        return $row;
    }, $topAdimplentes), 0, 10);

    $topInadimplentes = array_values($bad);
    usort($topInadimplentes, static function (array $a, array $b): int {
        $cmp = $b['open_total'] <=> $a['open_total'];
        return $cmp !== 0 ? $cmp : ($b['open_count'] <=> $a['open_count']);
    });
    $topInadimplentes = array_slice(array_map(static function (array $row): array {
        $row['open_total'] = round((float) $row['open_total'], 2);
        return $row;
    }, $topInadimplentes), 0, 10);

    $realizationTotal = (float) ($extrato['realization_total'] ?? 0);

    return [
        'kpis' => [
            'entries_total' => round((float) ($extrato['credits_total'] ?? 0), 2),
            'debit_total' => round(abs((float) ($extrato['debits_total'] ?? 0)), 2),
            'realization_total' => round($realizationTotal, 2),
            'fees_total' => round((float) ($extrato['total_fee_value'] ?? 0), 2),
            'net_total' => round((float) ($extrato['net_total'] ?? 0), 2),
            'balance_available' => isset($extrato['balance_available']) ? (float) $extrato['balance_available'] : null,
            'paid_count' => count($paidPayments),
            'open_count' => count($openPayments),
        ],
        'daily_series' => $dailySeries,
        'top_adimplentes' => $topAdimplentes,
        'top_inadimplentes' => $topInadimplentes,
    ];
}

function fetch_asaas_transactions(
    AsaasClient $asaas,
    string $from,
    string $to,
    int $maxPages,
    array &$warnings
): array
{
    $items = [];
    $offset = 0;
    $fetchedTotal = 0;

    for ($page = 0; $page < $maxPages; $page++) {
        $res = $asaas->listFinancialTransactions($from, $to, 100, $offset, 'asc');
        if (!($res['ok'] ?? false)) {
            $warnings[] = 'Falha ao consultar extrato financeiro no Asaas: ' . (($res['error'] ?? '') ?: 'sem detalhes');
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
            $items[] = normalize_asaas_transaction($tx);
        }
        $fetchedTotal += count($list);

        if (count($list) < 100) {
            break;
        }
        $offset += 100;
    }

    $creditItems = [];
    $debitItems = [];
    $realizationItems = [];
    $feeItems = [];
    $credits = 0.0;
    $debits = 0.0;
    $debitsExcludingRealization = 0.0;
    $realizationTotal = 0.0;
    foreach ($items as $item) {
        $value = (float) ($item['value'] ?? 0);
        if ($value >= 0) {
            $credits += $value;
            $creditItems[] = $item;
        } else {
            $debits += $value;
            if (is_realizacao_inter_ci($item)) {
                $item['is_realizacao_inter_ci'] = true;
                $item['description'] = 'REALIZAÇÃO INTER CI';
                $realizationItems[] = $item;
                $realizationTotal += abs($value);
            } else {
                $debitItems[] = $item;
                $debitsExcludingRealization += $value;
            }
        }

        $type = strtoupper((string) ($item['type'] ?? ''));
        if (str_contains($type, 'FEE') || str_contains($type, 'PROMOTIONAL_CODE')) {
            $feeItems[] = $item;
        }
    }

    return [
        'count' => count($items),
        'from' => $from,
        'to' => $to,
        'total_value' => round($credits + $debits, 2),
        'credits_total' => round($credits, 2),
        'debits_total' => round($debits, 2),
        'debits_excluding_realization_total' => round($debitsExcludingRealization, 2),
        'realization_total' => round($realizationTotal, 2),
        'net_total' => round($credits + $debits, 2),
        'total_net_value' => round($credits + $debits, 2),
        'total_fee_value' => round(array_reduce($feeItems, static function (float $acc, array $item): float {
            $value = (float) ($item['value'] ?? 0);
            return $value < 0 ? $acc + abs($value) : $acc;
        }, 0.0), 2),
        'fetched_total' => $fetchedTotal,
        'items' => $items,
        'credit_items' => $creditItems,
        'debit_items' => $debitItems,
        'realization_items' => $realizationItems,
        'fee_items' => $feeItems,
    ];
}

try {
    $asaas = new AsaasClient(new HttpClient());
    $supabase = new SupabaseClient(new HttpClient());
    $warnings = [];
    $customerStudentMap = build_customer_student_map($supabase, $warnings);

    $maxPages = 60;
    $from = normalize_date((string) ($_GET['from'] ?? '')) ?? (date('Y') . '-01-01');
    $to = normalize_date((string) ($_GET['to'] ?? '')) ?? date('Y-m-d');

    if ($from > $to) {
        Helpers::json(['ok' => false, 'error' => 'Período inválido para extrato Asaas.'], 422);
    }

    $extrato = fetch_asaas_transactions($asaas, $from, $to, $maxPages, $warnings);
    $balance = null;
    $balanceRes = $asaas->getFinanceBalance();
    if ($balanceRes['ok'] ?? false) {
        $balance = isset($balanceRes['data']['balance']) ? (float) $balanceRes['data']['balance'] : null;
    } else {
        $warnings[] = 'Não foi possível consultar saldo disponível no Asaas.';
    }

    $creditos = [
        'count' => count($extrato['credit_items']),
        'total_value' => $extrato['credits_total'],
        'items' => $extrato['credit_items'],
    ];
    $debitos = [
        'count' => count($extrato['debit_items']),
        'total_value' => $extrato['debits_excluding_realization_total'],
        'items' => $extrato['debit_items'],
    ];
    $realizacoes = [
        'count' => count($extrato['realization_items']),
        'total_value' => $extrato['realization_total'],
        'items' => $extrato['realization_items'],
    ];
    $taxas = [
        'count' => count($extrato['fee_items']),
        'total_value' => round(array_reduce($extrato['fee_items'], static function (float $acc, array $item): float {
            return $acc + (float) ($item['value'] ?? 0);
        }, 0.0), 2),
        'items' => $extrato['fee_items'],
    ];

    $hasAnyData = ($extrato['count'] ?? 0) > 0;
    if (!$hasAnyData && !empty($warnings)) {
        Helpers::json([
            'ok' => false,
            'error' => 'Não foi possível carregar extrato financeiro do Asaas.',
            'warnings' => $warnings,
        ], 502);
    }

    $paidStatuses = ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH', 'PAID'];
    $openStatuses = ['PENDING', 'OVERDUE', 'AWAITING_RISK_ANALYSIS'];
    $paidPaymentsAll = fetch_asaas_payments_by_statuses($asaas, $customerStudentMap, $paidStatuses, $maxPages, $warnings);
    $openPaymentsAll = fetch_asaas_payments_by_statuses($asaas, $customerStudentMap, $openStatuses, $maxPages, $warnings);
    $paidPayments = array_values(array_filter($paidPaymentsAll, static function (array $p) use ($from, $to): bool {
        $date = (string) ($p['date'] ?? '');
        return $date !== '' && $date >= $from && $date <= $to;
    }));
    $openPayments = array_values(array_filter($openPaymentsAll, static function (array $p) use ($from, $to): bool {
        $date = (string) ($p['date'] ?? '');
        return $date !== '' && $date >= $from && $date <= $to;
    }));

    $paymentMap = [];
    foreach (array_merge($paidPaymentsAll, $openPaymentsAll) as $p) {
        $pid = trim((string) ($p['id'] ?? ''));
        if ($pid !== '') {
            $paymentMap[$pid] = $p;
        }
    }
    foreach (['credit_items', 'debit_items', 'realization_items', 'fee_items'] as $bucket) {
        foreach ($extrato[$bucket] as &$tx) {
            $pid = trim((string) ($tx['payment_id'] ?? ''));
            if ($pid === '' || !isset($paymentMap[$pid])) {
                continue;
            }
            $p = $paymentMap[$pid];
            $tx['customer_id'] = (string) ($p['customer_id'] ?? '');
            $tx['customer_name'] = (string) (($p['customer_label'] ?? '') ?: ($p['customer_name'] ?? ''));
            $tx['billing_type'] = (string) (($p['billing_type'] ?? '') ?: ($tx['billing_type'] ?? '-'));
            $tx['student_name'] = (string) ($p['student_name'] ?? '');
        }
        unset($tx);
    }

    $extrato['balance_available'] = $balance;
    $analytics = build_analytics($extrato, $paidPayments, $openPayments);

    Helpers::json([
        'ok' => true,
        'source' => 'asaas_financial_transactions',
        'generated_at' => date('c'),
        'period' => ['from' => $from, 'to' => $to],
        'extrato' => [
            'count' => $extrato['count'],
            'credits_total' => $extrato['credits_total'],
            'debits_total' => $extrato['debits_excluding_realization_total'],
            'realization_total' => $extrato['realization_total'],
            'net_total' => $extrato['net_total'],
            'total_fee_value' => $extrato['total_fee_value'],
            'balance_available' => $balance,
        ],
        'groups' => [
            'creditos' => $creditos,
            'realizacoes' => $realizacoes,
            'debitos' => $debitos,
            'taxas' => $taxas,
        ],
        'analytics' => $analytics,
        'warnings' => $warnings,
    ]);
} catch (\Throwable $e) {
    error_log('[admin-asaas-data] ' . $e->getMessage());
    Helpers::json([
        'ok' => false,
        'error' => 'Falha ao buscar dados do Asaas.',
    ], 500);
}

