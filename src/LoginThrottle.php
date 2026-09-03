<?php

namespace App;

final class LoginThrottle
{
    private SupabaseClient $db;
    private string $secret;
    private string $ipAddress;
    /** @var string[] */
    private array $clearKeys = [];

    public function __construct(
        ?SupabaseClient $db = null,
        ?string $secret = null,
        ?string $ipAddress = null
    ) {
        $this->db = $db ?? new SupabaseClient(new HttpClient());
        $this->secret = trim((string) (
            $secret
            ?? Env::get('AUTH_RATE_LIMIT_SECRET', Env::get('SUPABASE_SERVICE_ROLE_KEY', ''))
        ));
        $this->ipAddress = $ipAddress ?? $this->resolveClientIp();
    }

    /**
     * @return array{ok: bool, allowed: bool, retry_after: int}
     */
    public function claim(string $scope, string $identifier): array
    {
        $scope = strtolower(trim($scope));
        if (!in_array($scope, ['guardian', 'admin'], true) || $this->secret === '') {
            return ['ok' => false, 'allowed' => false, 'retry_after' => 0];
        }

        $identifier = mb_strtolower(trim($identifier), 'UTF-8');
        if ($identifier === '') {
            $identifier = 'unknown';
        }
        $identifier = mb_substr($identifier, 0, 256, 'UTF-8');
        $ip = $this->ipAddress !== '' ? $this->ipAddress : 'unknown';
        $buckets = [
            $scope . '_ip',
            $scope . '_account',
            $scope . '_combo',
        ];
        $keys = [
            $this->key($buckets[0], $ip),
            $this->key($buckets[1], $identifier),
            $this->key($buckets[2], $ip . '|' . $identifier),
        ];
        $this->clearKeys = $keys;

        $result = $this->db->rpc('claim_login_attempt', [
            'p_key_hashes' => $keys,
            'p_buckets' => $buckets,
        ]);
        $data = $this->normalizeRpcResult($result['data'] ?? null);
        if (!($result['ok'] ?? false) || !is_array($data) || !($data['ok'] ?? false)) {
            return ['ok' => false, 'allowed' => false, 'retry_after' => 0];
        }

        return [
            'ok' => true,
            'allowed' => ($data['allowed'] ?? false) === true,
            'retry_after' => max(0, (int) ($data['retry_after'] ?? 0)),
        ];
    }

    public function clearAfterSuccess(): bool
    {
        if ($this->clearKeys === []) {
            return false;
        }
        $result = $this->db->rpc('clear_login_attempts', [
            'p_key_hashes' => $this->clearKeys,
        ]);
        $data = $this->normalizeRpcResult($result['data'] ?? null);

        return ($result['ok'] ?? false)
            && is_array($data)
            && ($data['ok'] ?? false) === true;
    }

    private function key(string $bucket, string $value): string
    {
        return hash_hmac('sha256', $bucket . '|' . $value, $this->secret);
    }

    private function resolveClientIp(): string
    {
        $candidate = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        return filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : '';
    }

    private function normalizeRpcResult(mixed $data): ?array
    {
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            return $data[0];
        }
        return is_array($data) ? $data : null;
    }
}
