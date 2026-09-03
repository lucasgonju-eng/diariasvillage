<?php
declare(strict_types=1);

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

use App\AdminAuth;
use App\AsaasClient;
use App\AsaasCustomerIdentity;
use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

Helpers::requireAdminRole(AdminAuth::ROLE_ADMIN);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    Helpers::json(['ok' => false, 'error' => 'Método inválido.'], 405);
}

const LEGACY_MONTHLY_CUTOFF = '2026-09-01';
const LEGACY_MONTHLY_OPEN_LOCAL_STATUSES = [
    'queued',
    'processing_asaas',
    'pending',
    'pending_asaas',
    'overdue',
    'awaiting_risk_analysis',
];
const LEGACY_MONTHLY_OPEN_REMOTE_STATUSES = [
    'PENDING',
    'OVERDUE',
    'AWAITING_RISK_ANALYSIS',
];
const LEGACY_MONTHLY_PAID_REMOTE_STATUSES = [
    'RECEIVED',
    'CONFIRMED',
    'RECEIVED_IN_CASH',
    'PAID',
];
const LEGACY_MONTHLY_CLOSED_REMOTE_STATUSES = [
    'CANCELED',
    'CANCELLED',
    'DELETED',
    'REFUNDED',
    'REFUND_REQUESTED',
    'REFUND_IN_PROGRESS',
];

/**
 * @return array<string, mixed>
 */
function legacy_monthly_relation(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }
    if (array_is_list($value)) {
        return is_array($value[0] ?? null) ? $value[0] : [];
    }
    return $value;
}

function legacy_monthly_amount_matches(float $local, float $remote): bool
{
    return abs($local - $remote) <= 0.009;
}

/**
 * @return array{ok: bool, data?: array<int, mixed>, status?: int, error?: string}
 */
function legacy_monthly_select_all(
    SupabaseClient $database,
    string $table,
    string $baseQuery,
    int $pageSize = 500
): array {
    $rows = [];
    for ($page = 0; $page < 100; $page++) {
        $offset = $page * $pageSize;
        $result = $database->select(
            $table,
            $baseQuery . '&limit=' . $pageSize . '&offset=' . $offset
        );
        if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
            return [
                'ok' => false,
                'status' => (int) ($result['status'] ?? 0),
                'error' => (string) ($result['error'] ?? 'Falha de paginação no Supabase.'),
            ];
        }
        $pageRows = $result['data'];
        array_push($rows, ...$pageRows);
        if (count($pageRows) < $pageSize) {
            return ['ok' => true, 'data' => $rows];
        }
    }

    return ['ok' => false, 'error' => 'Limite seguro de paginação excedido.'];
}

/**
 * @param array<string, int> $categories
 */
function legacy_monthly_add_category(array &$categories, string $category): void
{
    $categories[$category] = ($categories[$category] ?? 0) + 1;
}

try {
    $database = new SupabaseClient(new HttpClient());
    $asaas = new AsaasClient(new HttpClient());

    $plansResult = legacy_monthly_select_all(
        $database,
        'monthly_student_plans',
        'select=student_id,weekly_days&active=eq.true&order=student_id.asc'
    );
    if (!($plansResult['ok'] ?? false) || !is_array($plansResult['data'] ?? null)) {
        Helpers::json(['ok' => false, 'error' => 'Não foi possível carregar os planos mensalistas.'], 503);
    }

    $monthlyPlans = [];
    foreach ($plansResult['data'] as $plan) {
        if (!is_array($plan)) {
            continue;
        }
        $studentId = trim((string) ($plan['student_id'] ?? ''));
        if ($studentId !== '') {
            $monthlyPlans[$studentId] = (int) ($plan['weekly_days'] ?? 0);
        }
    }

    $statusFilter = implode(',', LEGACY_MONTHLY_OPEN_LOCAL_STATUSES);
    $paymentsResult = legacy_monthly_select_all(
        $database,
        'payments',
        'select=id,student_id,guardian_id,amount,payment_date,status,billing_type,asaas_payment_id,created_at,'
            . 'students(id,name,enrollment),'
            . 'guardians(id,student_id,parent_name,email,parent_document,asaas_customer_id)'
            . '&paid_at=is.null'
            . '&payment_date=lt.' . rawurlencode(LEGACY_MONTHLY_CUTOFF)
            . '&status=in.(' . $statusFilter . ')'
            . '&order=payment_date.asc,id.asc'
    );
    if (!($paymentsResult['ok'] ?? false) || !is_array($paymentsResult['data'] ?? null)) {
        Helpers::json(['ok' => false, 'error' => 'Não foi possível carregar as cobranças históricas.'], 503);
    }

    $items = [];
    $categories = [];
    $customerCache = [];
    $remoteLookups = 0;
    $customerLookups = 0;
    $localAmount = 0.0;
    $remoteOpenAmount = 0.0;

    foreach ($paymentsResult['data'] as $payment) {
        if (!is_array($payment)) {
            continue;
        }
        $studentId = trim((string) ($payment['student_id'] ?? ''));
        if ($studentId === '' || !array_key_exists($studentId, $monthlyPlans)) {
            continue;
        }

        $student = legacy_monthly_relation($payment['students'] ?? null);
        $guardian = legacy_monthly_relation($payment['guardians'] ?? null);
        $paymentId = trim((string) ($payment['id'] ?? ''));
        $asaasPaymentId = trim((string) ($payment['asaas_payment_id'] ?? ''));
        $amount = (float) ($payment['amount'] ?? 0);
        $localAmount += $amount;

        $item = [
            'payment_id' => $paymentId,
            'asaas_payment_id' => $asaasPaymentId,
            'student_id' => $studentId,
            'student_name' => trim((string) ($student['name'] ?? '')),
            'enrollment' => trim((string) ($student['enrollment'] ?? '')),
            'weekly_days' => $monthlyPlans[$studentId],
            'guardian_id' => trim((string) ($payment['guardian_id'] ?? '')),
            'guardian_name' => trim((string) ($guardian['parent_name'] ?? '')),
            'local_status' => strtolower(trim((string) ($payment['status'] ?? ''))),
            'local_amount' => round($amount, 2),
            'local_payment_date' => trim((string) ($payment['payment_date'] ?? '')),
            'billing_type' => trim((string) ($payment['billing_type'] ?? '')),
            'remote_status' => '',
            'remote_amount' => null,
            'remote_due_date' => '',
            'remote_customer_id' => '',
            'guardian_link_match' => false,
            'customer_link_match' => false,
            'identity_match' => false,
            'amount_match' => false,
            'category' => '',
            'requires_human_decision' => true,
        ];

        if ($asaasPaymentId === '') {
            $item['category'] = 'LOCAL_QUEUE_WITHOUT_REMOTE_ID';
            legacy_monthly_add_category($categories, $item['category']);
            $items[] = $item;
            continue;
        }

        $remoteLookups++;
        $remotePaymentResult = $asaas->getPayment($asaasPaymentId);
        if (!($remotePaymentResult['ok'] ?? false)) {
            $item['category'] = (int) ($remotePaymentResult['status'] ?? 0) === 404
                ? 'REMOTE_NOT_FOUND'
                : 'REMOTE_LOOKUP_FAILED';
            legacy_monthly_add_category($categories, $item['category']);
            $items[] = $item;
            continue;
        }

        $remotePayment = is_array($remotePaymentResult['data'] ?? null)
            ? $remotePaymentResult['data']
            : [];
        $remoteStatus = strtoupper(trim((string) ($remotePayment['status'] ?? '')));
        $remoteAmount = (float) ($remotePayment['value'] ?? 0);
        $remoteCustomerId = trim((string) ($remotePayment['customer'] ?? ''));
        $guardianId = trim((string) ($guardian['id'] ?? ''));
        $guardianStudentId = trim((string) ($guardian['student_id'] ?? ''));
        $linkedCustomerId = trim((string) ($guardian['asaas_customer_id'] ?? ''));

        $item['remote_status'] = $remoteStatus;
        $item['remote_amount'] = round($remoteAmount, 2);
        $item['remote_due_date'] = trim((string) ($remotePayment['dueDate'] ?? ''));
        $item['remote_customer_id'] = $remoteCustomerId;
        $item['guardian_link_match'] = $guardianId !== ''
            && hash_equals($guardianId, trim((string) ($payment['guardian_id'] ?? '')))
            && $guardianStudentId !== ''
            && hash_equals($guardianStudentId, $studentId);
        $item['customer_link_match'] = $linkedCustomerId !== ''
            && $remoteCustomerId !== ''
            && hash_equals($linkedCustomerId, $remoteCustomerId);
        $item['amount_match'] = legacy_monthly_amount_matches($amount, $remoteAmount);

        if ($remoteCustomerId !== '') {
            if (!array_key_exists($remoteCustomerId, $customerCache)) {
                $customerLookups++;
                $customerCache[$remoteCustomerId] = $asaas->getCustomer($remoteCustomerId);
            }
            $remoteCustomerResult = $customerCache[$remoteCustomerId];
            if (($remoteCustomerResult['ok'] ?? false) && is_array($remoteCustomerResult['data'] ?? null)) {
                $guardianName = trim((string) ($guardian['parent_name'] ?? ''));
                $guardianEmail = strtolower(trim((string) ($guardian['email'] ?? '')));
                $guardianDocument = (string) ($guardian['parent_document'] ?? '');
                $localIdentityComplete = $guardianName !== ''
                    && filter_var($guardianEmail, FILTER_VALIDATE_EMAIL) !== false
                    && AsaasCustomerIdentity::isValidCpfOrCnpj($guardianDocument);
                $item['identity_match'] = $localIdentityComplete
                    && AsaasCustomerIdentity::matchesRemoteCustomer(
                        $remoteCustomerResult['data'],
                        $guardianName,
                        $guardianEmail,
                        $guardianDocument
                    );
            }
        }

        $allSafetyChecksPass = $item['guardian_link_match']
            && $item['customer_link_match']
            && $item['identity_match']
            && $item['amount_match'];

        if (in_array($remoteStatus, LEGACY_MONTHLY_PAID_REMOTE_STATUSES, true)) {
            $item['category'] = $allSafetyChecksPass
                ? 'REMOTE_PAID_REQUIRES_RECONCILIATION'
                : 'REMOTE_PAID_IDENTITY_OR_VALUE_CONFLICT';
        } elseif (in_array($remoteStatus, LEGACY_MONTHLY_CLOSED_REMOTE_STATUSES, true)) {
            $item['category'] = $allSafetyChecksPass
                ? 'REMOTE_CLOSED_REQUIRES_RECONCILIATION'
                : 'REMOTE_CLOSED_IDENTITY_OR_VALUE_CONFLICT';
        } elseif (in_array($remoteStatus, LEGACY_MONTHLY_OPEN_REMOTE_STATUSES, true)) {
            if ($allSafetyChecksPass) {
                $item['category'] = 'REMOTE_OPEN_VERIFIED_REVIEW';
                $remoteOpenAmount += $remoteAmount;
            } else {
                $item['category'] = 'REMOTE_OPEN_IDENTITY_OR_VALUE_CONFLICT';
            }
        } else {
            $item['category'] = 'REMOTE_STATUS_UNKNOWN';
        }

        legacy_monthly_add_category($categories, $item['category']);
        $items[] = $item;
    }

    ksort($categories);
    Helpers::json([
        'ok' => true,
        'read_only' => true,
        'generated_at' => date('c'),
        'cutoff' => LEGACY_MONTHLY_CUTOFF,
        'summary' => [
            'active_monthly_students' => count($monthlyPlans),
            'local_records' => count($items),
            'remote_payment_lookups' => $remoteLookups,
            'remote_customer_lookups' => $customerLookups,
            'local_amount' => round($localAmount, 2),
            'verified_remote_open_amount' => round($remoteOpenAmount, 2),
            'categories' => $categories,
        ],
        'items' => $items,
    ]);
} catch (Throwable $error) {
    error_log('[admin-monthly-legacy-charge-audit] ' . $error->getMessage());
    Helpers::json(['ok' => false, 'error' => 'Falha ao auditar cobranças históricas.'], 500);
}
