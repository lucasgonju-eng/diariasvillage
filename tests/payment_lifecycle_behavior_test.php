<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Bootstrap.php';

use App\AsaasClient;
use App\Services\AsaasPaymentLifecycle;

$failures = [];

function check_lifecycle(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

final class FakeLifecycleAsaasClient extends AsaasClient
{
    /** @var array<int, array<string, mixed>> */
    public array $getResponses = [];
    /** @var array<int, array<string, mixed>> */
    public array $deleteResponses = [];
    /** @var array<int, array<string, mixed>> */
    public array $customerResponses = [];
    /** @var array<int, string> */
    public array $calls = [];

    public function __construct()
    {
    }

    public function getPayment(string $paymentId): array
    {
        $this->calls[] = 'get:' . $paymentId;
        return array_shift($this->getResponses) ?? ['ok' => false, 'status' => 0];
    }

    public function deletePayment(string $paymentId): array
    {
        $this->calls[] = 'delete:' . $paymentId;
        return array_shift($this->deleteResponses) ?? ['ok' => false, 'status' => 0];
    }

    public function getCustomer(string $customerId): array
    {
        $this->calls[] = 'customer:' . $customerId;
        return array_shift($this->customerResponses) ?? ['ok' => false, 'status' => 0];
    }
}

$openAsaas = new FakeLifecycleAsaasClient();
$openAsaas->getResponses[] = ['ok' => true, 'status' => 200, 'data' => ['status' => 'OVERDUE']];
$openAsaas->deleteResponses[] = ['ok' => true, 'status' => 200, 'data' => ['deleted' => true]];
$openResult = (new AsaasPaymentLifecycle($openAsaas))->cancelBeforeLocalMutation('pay_open');
check_lifecycle(($openResult['ok'] ?? false) === true, 'cobrança aberta deve ser cancelada');
check_lifecycle(
    $openAsaas->calls === ['get:pay_open', 'delete:pay_open'],
    'estado remoto deve ser consultado antes do cancelamento'
);

$paidAsaas = new FakeLifecycleAsaasClient();
$paidAsaas->getResponses[] = ['ok' => true, 'status' => 200, 'data' => ['status' => 'RECEIVED']];
$paidResult = (new AsaasPaymentLifecycle($paidAsaas))->cancelBeforeLocalMutation('pay_paid');
check_lifecycle(($paidResult['code'] ?? '') === 'ASAAS_PAYMENT_ALREADY_PAID', 'cobrança paga deve bloquear');
check_lifecycle($paidAsaas->calls === ['get:pay_paid'], 'cobrança paga não pode receber DELETE');

$offlineAsaas = new FakeLifecycleAsaasClient();
$offlineAsaas->getResponses[] = ['ok' => false, 'status' => 503, 'error' => 'offline'];
$offlineResult = (new AsaasPaymentLifecycle($offlineAsaas))->cancelBeforeLocalMutation('pay_unknown');
check_lifecycle(($offlineResult['code'] ?? '') === 'ASAAS_LOOKUP_FAILED', 'falha de consulta deve bloquear');
check_lifecycle($offlineAsaas->calls === ['get:pay_unknown'], 'falha de consulta não pode cancelar remotamente');

$closedAsaas = new FakeLifecycleAsaasClient();
$closedAsaas->getResponses[] = ['ok' => true, 'status' => 200, 'data' => ['status' => 'CANCELED']];
$closedResult = (new AsaasPaymentLifecycle($closedAsaas))->cancelBeforeLocalMutation('pay_closed');
check_lifecycle(($closedResult['already_closed'] ?? false) === true, 'cancelamento deve ser idempotente');
check_lifecycle($closedAsaas->calls === ['get:pay_closed'], 'cobrança já cancelada não precisa de novo DELETE');

$missingAsaas = new FakeLifecycleAsaasClient();
$missingAsaas->getResponses[] = ['ok' => false, 'status' => 404];
$missingResult = (new AsaasPaymentLifecycle($missingAsaas))->cancelBeforeLocalMutation('pay_missing');
check_lifecycle(($missingResult['remote_absent'] ?? false) === true, '404 remoto deve ser terminal e idempotente');

$compensationAsaas = new FakeLifecycleAsaasClient();
$compensationAsaas->deleteResponses[] = ['ok' => true, 'status' => 200];
$compensation = (new AsaasPaymentLifecycle($compensationAsaas))->compensateCreatedPayment('pay_new');
check_lifecycle(($compensation['ok'] ?? false) === true, 'compensação deve cancelar cobrança recém-criada');
check_lifecycle($compensationAsaas->calls === ['delete:pay_new'], 'compensação deve agir no ID recém-criado');

$failedCompensationAsaas = new FakeLifecycleAsaasClient();
$failedCompensationAsaas->deleteResponses[] = ['ok' => false, 'status' => 503];
$failedCompensation = (new AsaasPaymentLifecycle($failedCompensationAsaas))
    ->compensateCreatedPayment('pay_orphan');
check_lifecycle(
    ($failedCompensation['code'] ?? '') === 'ASAAS_COMPENSATION_FAILED',
    'falha de compensação deve ser explícita'
);
$rejectionLifecycle = new AsaasPaymentLifecycle(new FakeLifecycleAsaasClient());
check_lifecycle(
    $rejectionLifecycle->isDefinitiveCreationRejection(['status' => 422]),
    'rejeição 422 permite liberar claim sem risco de cobrança criada'
);
check_lifecycle(
    !$rejectionLifecycle->isDefinitiveCreationRejection(['status' => 0])
        && !$rejectionLifecycle->isDefinitiveCreationRejection(['status' => 503])
        && !$rejectionLifecycle->isDefinitiveCreationRejection(['status' => 429]),
    'timeout, 5xx e limite devem manter claim para conciliação'
);

$identityAsaas = new FakeLifecycleAsaasClient();
$identityAsaas->getResponses[] = [
    'ok' => true,
    'status' => 200,
    'data' => ['status' => 'PENDING', 'customer' => 'cus_family'],
];
$identityAsaas->customerResponses[] = [
    'ok' => true,
    'status' => 200,
    'data' => [
        'id' => 'cus_family',
        'name' => 'Maria da Silva',
        'email' => 'maria@example.com',
        'cpfCnpj' => '52998224725',
    ],
];
$identityAsaas->deleteResponses[] = ['ok' => true, 'status' => 200];
$identityResult = (new AsaasPaymentLifecycle($identityAsaas))->cancelBeforeLocalMutation(
    'pay_family',
    null,
    [
        'parent_name' => 'Maria da Silva',
        'email' => 'maria@example.com',
        'parent_document' => '529.982.247-25',
        'asaas_customer_id' => 'cus_family',
    ]
);
check_lifecycle(($identityResult['ok'] ?? false) === true, 'identidade composta exata deve permitir cancelamento');
check_lifecycle(
    $identityAsaas->calls === ['get:pay_family', 'customer:cus_family', 'delete:pay_family'],
    'cancelamento deve validar pagamento e cliente remoto antes do DELETE'
);

$conflictAsaas = new FakeLifecycleAsaasClient();
$conflictAsaas->getResponses[] = [
    'ok' => true,
    'status' => 200,
    'data' => ['status' => 'PENDING', 'customer' => 'cus_other'],
];
$conflictResult = (new AsaasPaymentLifecycle($conflictAsaas))->cancelBeforeLocalMutation(
    'pay_conflict',
    null,
    [
        'parent_name' => 'Maria da Silva',
        'email' => 'maria@example.com',
        'parent_document' => '52998224725',
        'asaas_customer_id' => 'cus_family',
    ]
);
check_lifecycle(
    ($conflictResult['code'] ?? '') === 'ASAAS_PAYMENT_IDENTITY_MISMATCH',
    'vínculo com outro cliente deve bloquear cancelamento'
);
check_lifecycle(
    $conflictAsaas->calls === ['get:pay_conflict'],
    'conflito de cliente não pode consultar ou cancelar dados de outra família'
);

$root = dirname(__DIR__);
$finance = file_get_contents($root . '/public/api/financeiro-pay.php');
$resend = file_get_contents($root . '/public/api/admin-resend-feb-charge.php');
$deletePayment = file_get_contents($root . '/public/api/admin-delete-payment.php');
$deletePending = file_get_contents($root . '/public/api/admin-delete-pendencia.php');
$pendingMigration = file_get_contents(
    $root . '/supabase/migrations/20260903021237_add_pending_registration_cancellation_state.sql'
);
$terminalMigration = file_get_contents(
    $root . '/supabase/migrations/20260903022047_lock_charge_replacements_and_terminal_cancellations.sql'
);
$noDeleteMigration = file_get_contents(
    $root . '/supabase/migrations/20260903022944_prevent_pending_registration_physical_deletion.sql'
);
$operationTokenMigration = file_get_contents(
    $root . '/supabase/migrations/20260903023317_add_asaas_operation_token.sql'
);
$pendingVerification = file_get_contents($root . '/public/pendencia-verify.php');
$financialSync = file_get_contents($root . '/public/api/admin-sync-charges-payments.php');
$receivedSync = file_get_contents($root . '/public/api/admin-sync-recebidas.php');
$webhookEndpoint = file_get_contents($root . '/public/api/asaas-webhook.php');
$createPaymentEndpoint = file_get_contents($root . '/public/api/create-payment.php');
$adminChargeEndpoint = file_get_contents($root . '/public/api/admin-charge.php');

check_lifecycle(
    is_string($finance)
        && strpos($finance, 'cancelBeforeLocalMutation(') < strpos($finance, '$asaas->createPayment(')
        && str_contains($finance, 'compensateCreatedPayment($effectiveAsaasPaymentId)')
        && str_contains($finance, "'status' => 'processing_asaas'")
        && str_contains($finance, '$operationToken = bin2hex(random_bytes(16))')
        && str_contains($finance, "'asaas_operation_token' => \$operationToken")
        && str_contains($finance, "'externalReference' => 'payment:' . \$paymentId"),
    'financeiro deve cancelar a anterior antes de substituir e compensar falha local'
);
check_lifecycle(
    is_string($resend)
        && strpos($resend, 'cancelBeforeLocalMutation(') < strpos($resend, '$asaas->createPayment(')
        && str_contains($resend, 'compensateCreatedPayment($effectiveAsaasPaymentId)')
        && str_contains($resend, "'status' => 'processing_asaas'")
        && str_contains($resend, '$operationToken = bin2hex(random_bytes(16))')
        && str_contains($resend, "'asaas_operation_token' => \$operationToken")
        && str_contains($resend, 'abs($remoteValue - $amount) > 0.009'),
    'reenvio deve cancelar a anterior antes de substituir e compensar falha local'
);
check_lifecycle(
    is_string($deletePayment)
        && str_contains($deletePayment, 'cancelBeforeLocalMutation(')
        && str_contains($deletePayment, "['status' => 'canceled']")
        && !str_contains($deletePayment, "\$client->delete('payments'"),
    'cancelamento de payment deve ser remoto primeiro e lógico localmente'
);
check_lifecycle(
    is_string($deletePending)
        && str_contains($deletePending, 'cancelBeforeLocalMutation(')
        && str_contains($deletePending, "'status' => 'canceled'")
        && !str_contains($deletePending, "\$client->delete('pendencia_de_cadastro'"),
    'cancelamento de pendência deve ser remoto primeiro e lógico localmente'
);
check_lifecycle(
    is_string($pendingMigration)
        && str_contains($pendingMigration, "check (status in ('pending', 'paid', 'canceled'))")
        && str_contains($pendingMigration, 'canceled_at timestamptz')
        && str_contains($pendingMigration, 'cancel_reason text'),
    'migration deve preservar o estado auditável das pendências'
);
check_lifecycle(
    is_string($terminalMigration)
        && str_contains($terminalMigration, "old.status = 'canceled'")
        && str_contains($terminalMigration, 'PENDENCIA_CANCELED_IS_TERMINAL')
        && str_contains($terminalMigration, "'processing_asaas'"),
    'banco deve tornar cancelamento terminal e manter claim na unicidade aberta'
);
check_lifecycle(
    is_string($pendingVerification)
        && str_contains($pendingVerification, "\$pendenciaStatus === 'canceled'")
        && str_contains($pendingVerification, 'não pode gerar uma nova cobrança'),
    'token legado não pode reativar pendência cancelada'
);
check_lifecycle(
    is_string($noDeleteMigration)
        && str_contains($noDeleteMigration, "tg_op = 'DELETE'")
        && str_contains($noDeleteMigration, 'PENDENCIA_PHYSICAL_DELETE_FORBIDDEN')
        && str_contains($noDeleteMigration, 'before insert or update or delete'),
    'banco deve bloquear exclusão física de pendências financeiras'
);
check_lifecycle(
    is_string($financialSync)
        && !str_contains($financialSync, "\$client->delete('payments'")
        && !str_contains($financialSync, "\$client->delete('pendencia_de_cadastro'")
        && str_contains($financialSync, 'findPaymentByExternalReference($expectedReference)')
        && str_contains($financialSync, "['hasMore'] ?? true")
        && str_contains($financialSync, "['totalCount'] ?? -1")
        && str_contains($financialSync, '&status=eq.processing_asaas&asaas_operation_token=eq.'),
    'sincronização deve preservar histórico e reconciliar resultado remoto ambíguo'
);
check_lifecycle(
    is_string($operationTokenMigration)
        && str_contains($operationTokenMigration, 'asaas_operation_token text')
        && str_contains($operationTokenMigration, 'uq_payments_asaas_operation_token')
        && str_contains($operationTokenMigration, "'^[0-9a-f]{32}$'"),
    'cada tentativa remota deve possuir token único persistido antes da chamada'
);
check_lifecycle(
    is_string($createPaymentEndpoint)
        && is_string($adminChargeEndpoint)
        && str_contains($createPaymentEndpoint, 'queued,processing_asaas,pending')
        && str_contains($adminChargeEndpoint, 'queued,processing_asaas,pending'),
    'novas cobranças devem respeitar claim financeiro em processamento'
);
check_lifecycle(
    is_string($receivedSync)
        && str_contains($receivedSync, 'status=eq.processing_asaas')
        && str_contains($receivedSync, 'asaas_operation_token=eq.')
        && str_contains($receivedSync, "'asaas_operation_token' => null")
        && str_contains($receivedSync, "'access_code' => \$accessCode")
        && str_contains($receivedSync, 'confirmarGradeNoPagamento($diariaId)')
        && strpos($receivedSync, 'status=eq.processing_asaas')
            < strpos($receivedSync, "\$client->insert('payments'"),
    'sync de recebidas deve reconciliar tentativa ambígua antes de importar novo payment'
);
check_lifecycle(
    is_string($webhookEndpoint)
        && str_contains($webhookEndpoint, '$operationRecoveryToken')
        && str_contains($webhookEndpoint, '&status=eq.processing_asaas&asaas_operation_token=eq.')
        && str_contains($webhookEndpoint, "\$paymentUpdatePayload['asaas_payment_id'] = \$paymentId"),
    'webhook deve adquirir tentativa ambígua e executar a liberação operacional normal'
);

if ($failures !== []) {
    fwrite(STDERR, "Falhas no ciclo financeiro:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK: cancelamento remoto, fail-closed e compensação financeira validados.\n";
