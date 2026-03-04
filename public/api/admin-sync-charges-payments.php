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

function asaas_status_is_canceled(string $status): bool
{
    return in_array($status, ['CANCELED', 'DELETED', 'REFUNDED', 'REFUND_REQUESTED'], true);
}

try {
    if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
        Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
    }

    Helpers::requirePost();

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
    ];

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
        'select=id,paid_at,asaas_payment_id,asaas_invoice_url&or=(asaas_payment_id.not.is.null,asaas_invoice_url.not.is.null)&limit=5000'
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
        if ($pendenciaId === '') {
            continue;
        }

        $asaasData = null;
        if ($asaasId !== '') {
            $response = $asaas->getPayment($asaasId);
            if (($response['ok'] ?? false) && is_array($response['data'] ?? null)) {
                $asaasData = $response['data'];
            }
        }
        if (!$asaasData && $invoiceUrl !== '') {
            $response = $asaas->findPaymentByInvoiceUrl($invoiceUrl);
            $list = ($response['ok'] ?? false) && is_array($response['data']['data'] ?? null)
                ? $response['data']['data']
                : [];
            $asaasData = $list[0] ?? null;
        }

        if (!$asaasData || !is_array($asaasData)) {
            $update = $client->update('pendencia_de_cadastro', 'id=eq.' . urlencode($pendenciaId), [
                'asaas_payment_id' => null,
                'asaas_invoice_url' => null,
            ]);
            if ($update['ok'] ?? false) {
                $summary['pendencias_unlinked']++;
            }
            continue;
        }

        $asaasStatus = (string) ($asaasData['status'] ?? '');
        if (asaas_status_is_paid($asaasStatus)) {
            $update = $client->update('pendencia_de_cadastro', 'id=eq.' . urlencode($pendenciaId), [
                'paid_at' => date('c'),
                'asaas_payment_id' => $asaasData['id'] ?? $asaasId ?: null,
                'asaas_invoice_url' => $asaasData['invoiceUrl'] ?? $invoiceUrl ?: null,
            ]);
            if ($update['ok'] ?? false) {
                $summary['pendencias_paid_updated']++;
            }
        }
    }

    Helpers::json([
        'ok' => true,
        'summary' => $summary,
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
