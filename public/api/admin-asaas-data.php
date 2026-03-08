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
    $feeItems = [];
    $credits = 0.0;
    $debits = 0.0;
    foreach ($items as $item) {
        $value = (float) ($item['value'] ?? 0);
        if ($value >= 0) {
            $credits += $value;
            $creditItems[] = $item;
        } else {
            $debits += $value;
            $debitItems[] = $item;
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
        'fee_items' => $feeItems,
    ];
}

try {
    $asaas = new AsaasClient(new HttpClient());
    $warnings = [];
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
        'total_value' => $extrato['debits_total'],
        'items' => $extrato['debit_items'],
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

    Helpers::json([
        'ok' => true,
        'source' => 'asaas_financial_transactions',
        'generated_at' => date('c'),
        'period' => ['from' => $from, 'to' => $to],
        'extrato' => [
            'count' => $extrato['count'],
            'credits_total' => $extrato['credits_total'],
            'debits_total' => $extrato['debits_total'],
            'net_total' => $extrato['net_total'],
            'total_fee_value' => $extrato['total_fee_value'],
            'balance_available' => $balance,
        ],
        'groups' => [
            'creditos' => $creditos,
            'debitos' => $debitos,
            'taxas' => $taxas,
        ],
        'warnings' => $warnings,
    ]);
} catch (\Throwable $e) {
    error_log('[admin-asaas-data] ' . $e->getMessage());
    Helpers::json([
        'ok' => false,
        'error' => 'Falha ao buscar dados do Asaas.',
    ], 500);
}

