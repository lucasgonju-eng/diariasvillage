<?php
declare(strict_types=1);

namespace App\Services;

use App\AsaasClient;
use App\AsaasCustomerIdentity;

final class AsaasPaymentLifecycle
{
    private const PAID_STATUSES = [
        'RECEIVED',
        'CONFIRMED',
        'RECEIVED_IN_CASH',
    ];

    private const CLOSED_STATUSES = [
        'CANCELED',
        'CANCELLED',
        'DELETED',
        'REFUNDED',
        'REFUND_REQUESTED',
        'REFUND_IN_PROGRESS',
    ];

    private const CANCELABLE_STATUSES = [
        'PENDING',
        'OVERDUE',
        'AWAITING_RISK_ANALYSIS',
    ];

    public function __construct(private AsaasClient $asaas)
    {
    }

    /**
     * Cancela uma cobrança remota antes de substituição ou cancelamento local.
     *
     * @param array<string, mixed>|null $knownResponse Resposta prévia de getPayment.
     * @param array<string, mixed>|null $expectedIdentity Identidade local esperada.
     * @return array<string, mixed>
     */
    public function cancelBeforeLocalMutation(
        string $paymentId,
        ?array $knownResponse = null,
        ?array $expectedIdentity = null
    ): array {
        $paymentId = trim($paymentId);
        if ($paymentId === '') {
            return [
                'ok' => false,
                'code' => 'ASAAS_PAYMENT_ID_REQUIRED',
                'error' => 'ID da cobrança Asaas não informado.',
            ];
        }

        $inspection = $this->inspect($paymentId, $knownResponse, $expectedIdentity);
        if (!($inspection['ok'] ?? false)) {
            return $inspection;
        }
        if (($inspection['already_closed'] ?? false) || ($inspection['remote_absent'] ?? false)) {
            return $inspection;
        }

        $delete = $this->asaas->deletePayment($paymentId);
        if (!($delete['ok'] ?? false) && (int) ($delete['status'] ?? 0) !== 404) {
            return [
                'ok' => false,
                'code' => 'ASAAS_CANCEL_FAILED',
                'status' => $inspection['status'] ?? '',
                'error' => 'Não foi possível cancelar a cobrança no Asaas.',
            ];
        }

        return [
            'ok' => true,
            'canceled' => true,
            'status' => $inspection['status'] ?? '',
        ];
    }

    /**
     * Remove uma cobrança recém-criada quando a persistência local falha.
     *
     * @return array<string, mixed>
     */
    public function compensateCreatedPayment(string $paymentId): array
    {
        $paymentId = trim($paymentId);
        if ($paymentId === '') {
            return [
                'ok' => false,
                'code' => 'ASAAS_PAYMENT_ID_REQUIRED',
                'error' => 'Cobrança remota criada sem ID; revisão manual obrigatória.',
            ];
        }

        $delete = $this->asaas->deletePayment($paymentId);
        if (($delete['ok'] ?? false) || (int) ($delete['status'] ?? 0) === 404) {
            return ['ok' => true, 'canceled' => true];
        }

        return [
            'ok' => false,
            'code' => 'ASAAS_COMPENSATION_FAILED',
            'error' => 'Falha ao cancelar a cobrança remota após erro local.',
        ];
    }

    /**
     * Só uma rejeição HTTP definitiva permite liberar o claim sem reconciliação.
     *
     * @param array<string, mixed> $response
     */
    public function isDefinitiveCreationRejection(array $response): bool
    {
        $status = (int) ($response['status'] ?? 0);
        return $status >= 400
            && $status < 500
            && !in_array($status, [408, 409, 425, 429], true);
    }

    /**
     * @param array<string, mixed>|null $knownResponse
     * @param array<string, mixed>|null $expectedIdentity
     * @return array<string, mixed>
     */
    private function inspect(string $paymentId, ?array $knownResponse, ?array $expectedIdentity): array
    {
        $response = $knownResponse ?? $this->asaas->getPayment($paymentId);
        if (!($response['ok'] ?? false)) {
            if ((int) ($response['status'] ?? 0) === 404) {
                return ['ok' => true, 'remote_absent' => true, 'status' => 'NOT_FOUND'];
            }
            return [
                'ok' => false,
                'code' => 'ASAAS_LOOKUP_FAILED',
                'error' => 'Não foi possível confirmar o estado da cobrança no Asaas.',
            ];
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        if ($expectedIdentity !== null) {
            $identityCheck = $this->validatePaymentIdentity($data, $expectedIdentity);
            if (!($identityCheck['ok'] ?? false)) {
                return $identityCheck;
            }
        }

        $status = strtoupper(trim((string) ($data['status'] ?? '')));
        if (in_array($status, self::PAID_STATUSES, true)) {
            return [
                'ok' => false,
                'code' => 'ASAAS_PAYMENT_ALREADY_PAID',
                'status' => $status,
                'error' => 'A cobrança já foi paga no Asaas.',
            ];
        }
        if (in_array($status, self::CLOSED_STATUSES, true)) {
            return ['ok' => true, 'already_closed' => true, 'status' => $status];
        }
        if (!in_array($status, self::CANCELABLE_STATUSES, true)) {
            return [
                'ok' => false,
                'code' => 'ASAAS_PAYMENT_STATUS_UNKNOWN',
                'status' => $status,
                'error' => 'Estado remoto desconhecido; operação bloqueada para revisão.',
            ];
        }

        return ['ok' => true, 'cancelable' => true, 'status' => $status];
    }

    /**
     * @param array<string, mixed> $payment
     * @param array<string, mixed> $expectedIdentity
     * @return array<string, mixed>
     */
    private function validatePaymentIdentity(array $payment, array $expectedIdentity): array
    {
        $remoteCustomerId = trim((string) ($payment['customer'] ?? ''));
        $linkedCustomerId = trim((string) ($expectedIdentity['asaas_customer_id'] ?? ''));
        if (
            $remoteCustomerId === ''
            || ($linkedCustomerId !== '' && $remoteCustomerId !== $linkedCustomerId)
        ) {
            return [
                'ok' => false,
                'code' => 'ASAAS_PAYMENT_IDENTITY_MISMATCH',
                'error' => 'A cobrança não pertence ao responsável validado.',
            ];
        }

        $customerResponse = $this->asaas->getCustomer($remoteCustomerId);
        if (!($customerResponse['ok'] ?? false) || !is_array($customerResponse['data'] ?? null)) {
            return [
                'ok' => false,
                'code' => 'ASAAS_CUSTOMER_LOOKUP_FAILED',
                'error' => 'Não foi possível validar a identidade do cliente no Asaas.',
            ];
        }

        $name = trim((string) (
            $expectedIdentity['parent_name']
            ?? $expectedIdentity['guardian_name']
            ?? ''
        ));
        $email = trim((string) (
            $expectedIdentity['email']
            ?? $expectedIdentity['guardian_email']
            ?? ''
        ));
        $document = trim((string) (
            $expectedIdentity['parent_document']
            ?? $expectedIdentity['guardian_cpf']
            ?? ''
        ));
        if (!AsaasCustomerIdentity::matchesRemoteCustomer(
            $customerResponse['data'],
            $name,
            $email,
            $document
        )) {
            return [
                'ok' => false,
                'code' => 'ASAAS_PAYMENT_IDENTITY_MISMATCH',
                'error' => 'A identidade da cobrança diverge do responsável local.',
            ];
        }

        return ['ok' => true];
    }
}
