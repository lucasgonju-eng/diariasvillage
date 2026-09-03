<?php

namespace App;

final class GuardianSession
{
    private SupabaseClient $db;

    public function __construct(?SupabaseClient $db = null)
    {
        $this->db = $db ?? new SupabaseClient(new HttpClient());
    }

    /**
     * @return array{ok: bool, session_version?: int, updated_guardians?: int, code?: string}
     */
    public function rotate(string $guardianId, string $authUserId, int $expectedVersion): array
    {
        $result = $this->db->rpc('rotate_guardian_account_session', [
            'p_guardian_id' => $guardianId,
            'p_auth_user_id' => $authUserId !== '' ? $authUserId : null,
            'p_expected_version' => $expectedVersion,
        ]);
        $data = $this->normalizeRpcResult($result['data'] ?? null);
        if (!($result['ok'] ?? false) || !is_array($data) || !($data['ok'] ?? false)) {
            return [
                'ok' => false,
                'code' => (string) ($data['code'] ?? 'SESSION_ROTATION_FAILED'),
            ];
        }

        return [
            'ok' => true,
            'session_version' => (int) ($data['session_version'] ?? 0),
            'updated_guardians' => (int) ($data['updated_guardians'] ?? 0),
        ];
    }

    private function normalizeRpcResult(mixed $data): ?array
    {
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            return $data[0];
        }
        return is_array($data) ? $data : null;
    }
}
