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

function pick_asaas_value(array $data, array $keys): string
{
    foreach ($keys as $key) {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }
    return '';
}

function normalize_asaas_payment(array $payment): array
{
    $value = (float) ($payment['value'] ?? 0);
    $netValue = (float) ($payment['netValue'] ?? 0);
    $paidAt = pick_asaas_value($payment, ['clientPaymentDate', 'paymentDate', 'confirmedDate']);
    $invoiceUrl = pick_asaas_value($payment, ['invoiceUrl', 'bankSlipUrl']);
    $dueDate = trim((string) ($payment['dueDate'] ?? ''));
    $customerId = trim((string) ($payment['customer'] ?? ''));
    $customerName = trim((string) ($payment['customerName'] ?? ''));

    return [
        'id' => trim((string) ($payment['id'] ?? '')),
        'status' => trim((string) ($payment['status'] ?? '')),
        'customer_id' => $customerId,
        'customer_name' => $customerName,
        'description' => trim((string) ($payment['description'] ?? '')),
        'billing_type' => trim((string) ($payment['billingType'] ?? '')),
        'value' => $value,
        'net_value' => $netValue,
        'due_date' => $dueDate,
        'paid_at' => $paidAt,
        'invoice_url' => $invoiceUrl,
        'external_reference' => trim((string) ($payment['externalReference'] ?? '')),
        'date_created' => trim((string) ($payment['dateCreated'] ?? '')),
    ];
}

function fetch_asaas_group(AsaasClient $asaas, array $statuses, int $maxPages, array &$warnings): array
{
    $items = [];
    $statusCounts = [];
    $fetchedTotal = 0;

    foreach ($statuses as $status) {
        $offset = 0;
        for ($page = 0; $page < $maxPages; $page++) {
            $res = $asaas->listPaymentsByStatus($status, 100, $offset);
            if (!($res['ok'] ?? false)) {
                $warnings[] = 'Falha ao consultar status ' . $status . ' no Asaas: ' . (($res['error'] ?? '') ?: 'sem detalhes');
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
                $items[] = normalize_asaas_payment($payment);
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            }
            $fetchedTotal += count($list);

            if (count($list) < 100) {
                break;
            }
            $offset += 100;
        }
    }

    usort($items, static function (array $a, array $b): int {
        $aTime = strtotime((string) ($a['paid_at'] ?: $a['due_date'] ?: $a['date_created'])) ?: 0;
        $bTime = strtotime((string) ($b['paid_at'] ?: $b['due_date'] ?: $b['date_created'])) ?: 0;
        return $bTime <=> $aTime;
    });

    $totalValue = 0.0;
    foreach ($items as $item) {
        $totalValue += (float) ($item['value'] ?? 0);
    }

    return [
        'count' => count($items),
        'total_value' => $totalValue,
        'status_counts' => $statusCounts,
        'fetched_total' => $fetchedTotal,
        'items' => $items,
    ];
}

try {
    $asaas = new AsaasClient(new HttpClient());
    $warnings = [];
    $maxPages = 60;

    $pagos = fetch_asaas_group($asaas, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH', 'PAID'], $maxPages, $warnings);
    $pendentes = fetch_asaas_group($asaas, ['PENDING', 'AWAITING_RISK_ANALYSIS'], $maxPages, $warnings);
    $vencidos = fetch_asaas_group($asaas, ['OVERDUE'], $maxPages, $warnings);

    $hasAnyData = ($pagos['count'] ?? 0) > 0 || ($pendentes['count'] ?? 0) > 0 || ($vencidos['count'] ?? 0) > 0;
    if (!$hasAnyData && !empty($warnings)) {
        Helpers::json([
            'ok' => false,
            'error' => 'Não foi possível carregar dados do Asaas.',
            'warnings' => $warnings,
        ], 502);
    }

    Helpers::json([
        'ok' => true,
        'source' => 'asaas_direct',
        'generated_at' => date('c'),
        'groups' => [
            'pagos' => $pagos,
            'pendentes' => $pendentes,
            'vencidos' => $vencidos,
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

