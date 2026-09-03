<?php

namespace App\Services;

use App\HttpClient;
use App\SupabaseClient;

final class AsaasWebhookInbox
{
    private SupabaseClient $client;

    public function __construct(?SupabaseClient $client = null)
    {
        $this->client = $client ?? new SupabaseClient(new HttpClient());
    }

    public function claim(
        string $eventId,
        string $eventType,
        string $paymentId,
        array $payload,
        string $rawPayload
    ): array {
        $result = $this->client->rpc('claim_asaas_webhook_event', [
            'p_event_id' => $eventId,
            'p_event_type' => $eventType,
            'p_payment_id' => $paymentId,
            'p_payload' => $payload,
            'p_payload_sha256' => hash('sha256', $rawPayload),
        ]);

        if (!($result['ok'] ?? false)) {
            return ['ok' => false, 'code' => 'WEBHOOK_INBOX_UNAVAILABLE'];
        }

        $data = $this->normalizeRpcResult($result['data'] ?? null);
        if (!is_array($data) || ($data['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'code' => (string) ($data['code'] ?? 'WEBHOOK_INBOX_REJECTED'),
            ];
        }

        return $data;
    }

    public function complete(string $eventId, string $status = 'PROCESSED'): bool
    {
        $result = $this->client->rpc('complete_asaas_webhook_event', [
            'p_event_id' => $eventId,
            'p_status' => $status,
        ]);
        $data = $this->normalizeRpcResult($result['data'] ?? null);

        return ($result['ok'] ?? false)
            && is_array($data)
            && ($data['ok'] ?? false) === true;
    }

    public function fail(string $eventId, string $error): bool
    {
        $result = $this->client->rpc('fail_asaas_webhook_event', [
            'p_event_id' => $eventId,
            'p_error' => mb_substr($error, 0, 1000),
        ]);
        $data = $this->normalizeRpcResult($result['data'] ?? null);

        return ($result['ok'] ?? false)
            && is_array($data)
            && ($data['ok'] ?? false) === true;
    }

    private function normalizeRpcResult($data): ?array
    {
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            return $data[0];
        }
        return is_array($data) ? $data : null;
    }
}
