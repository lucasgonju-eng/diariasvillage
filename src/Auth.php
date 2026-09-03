<?php

namespace App;

class Auth
{
    private SupabaseClient $db;
    private ?SupabaseAuth $supabaseAuth;

    public function __construct(SupabaseClient $db, ?SupabaseAuth $supabaseAuth = null)
    {
        $this->db = $db;
        $this->supabaseAuth = $supabaseAuth ?? new SupabaseAuth(new HttpClient());
    }

    public function login(string $cpf, string $password): array
    {
        $cpfDigits = preg_replace('/\D+/', '', $cpf) ?? '';
        if (
            strlen($cpfDigits) !== 11
            || !AsaasCustomerIdentity::isValidCpfOrCnpj($cpfDigits)
        ) {
            return ['ok' => false, 'error' => 'Credenciais inválidas.'];
        }
        $result = $this->buscarGuardiansPorCpf($cpfDigits);
        if (!$result['ok'] || empty($result['data'])) {
            $pendencia = $this->buscarPendenciaPorCpf($cpfDigits);
            if ($pendencia['ok'] && !empty($pendencia['data'])) {
                return [
                    'ok' => false,
                    'error' => 'Cadastro pendente. A secretaria vai concluir e avisar por e-mail.',
                ];
            }
            return ['ok' => false, 'error' => 'Credenciais inválidas.'];
        }

        $identity = GuardianAccountIdentity::analyze($result['data']);
        if (!($identity['ok'] ?? false)) {
            error_log('[auth] identidade de CPF bloqueada: ' . (string) ($identity['code'] ?? 'UNKNOWN'));
            return ['ok' => false, 'error' => 'Credenciais inválidas.'];
        }

        if (($identity['mode'] ?? '') === 'supabase_auth') {
            $expectedAuthUserId = trim((string) ($identity['auth_user_id'] ?? ''));
            $accountResult = $this->db->selectAll(
                'guardians',
                'select=*&auth_user_id=eq.' . rawurlencode($expectedAuthUserId) . '&order=id.asc'
            );
            $accountIdentity = (($accountResult['ok'] ?? false) && is_array($accountResult['data'] ?? null))
                ? GuardianAccountIdentity::analyze($accountResult['data'])
                : ['ok' => false, 'code' => 'GUARDIAN_AUTH_ACCOUNT_LOOKUP_FAILED'];
            if (
                !($accountIdentity['ok'] ?? false)
                || ($accountIdentity['mode'] ?? '') !== 'supabase_auth'
                || !hash_equals($cpfDigits, (string) ($accountIdentity['document'] ?? ''))
            ) {
                error_log('[auth] vínculo Auth ampliado bloqueado: ' . (string) ($accountIdentity['code'] ?? 'UNKNOWN'));
                return ['ok' => false, 'error' => 'Credenciais inválidas.'];
            }
            $identity = $accountIdentity;
            foreach (($identity['emails'] ?? []) as $email) {
                $signIn = $this->supabaseAuth->signIn((string) $email, $password);
                if ($signIn['ok'] && !empty($signIn['data'])) {
                    $signedInUserId = trim((string) ($signIn['data']['user']['id'] ?? ''));
                    if ($signedInUserId !== '' && hash_equals($expectedAuthUserId, $signedInUserId)) {
                        foreach (($identity['guardians'] ?? []) as $guardian) {
                            if (strtolower(trim((string) ($guardian['email'] ?? ''))) === (string) $email) {
                                return ['ok' => true, 'user' => $guardian];
                            }
                        }
                        return ['ok' => true, 'user' => $identity['guardians'][0]];
                    }
                    error_log('[auth] resposta Auth divergente do vínculo local esperado');
                    continue;
                }
                $err = ($signIn['data'] ?? [])['error_description'] ?? ($signIn['error'] ?? '');
                if (stripos((string) $err, 'email') !== false && stripos((string) $err, 'confirm') !== false) {
                    return ['ok' => false, 'error' => 'E-mail ainda não verificado.'];
                }
            }

            // Contas ligadas ao Supabase Auth nunca voltam ao hash local antigo.
            return ['ok' => false, 'error' => 'Credenciais inválidas.'];
        }

        $legacyUser = $identity['guardians'][0] ?? null;
        if (!is_array($legacyUser) || empty($legacyUser['password_hash'])) {
            return ['ok' => false, 'error' => 'Cadastro sem senha. Faça o primeiro acesso para criar.'];
        }
        if (
            !password_verify($password, (string) $legacyUser['password_hash'])
            || empty($legacyUser['verified_at'])
        ) {
            return ['ok' => false, 'error' => 'Credenciais inválidas.'];
        }
        return ['ok' => true, 'user' => $legacyUser];
    }

    private function buscarGuardiansPorCpf(string $cpfDigits): array
    {
        $result = $this->db->selectAll('guardians', 'select=*&order=id.asc');
        if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
            return $result;
        }

        $matches = array_values(array_filter($result['data'], static function (mixed $guardian) use ($cpfDigits): bool {
            return is_array($guardian)
                && AsaasCustomerIdentity::normalizeDocument(
                    (string) ($guardian['parent_document'] ?? '')
                ) === $cpfDigits;
        }));
        $result['data'] = $matches;
        return $result;
    }

    private function buscarPendenciaPorCpf(string $cpfDigits): array
    {
        $masked = $this->formatCpf($cpfDigits);

        $attempts = [
            'guardian_cpf=eq.' . urlencode($cpfDigits) . '&select=id,verified_at',
            'guardian_cpf=eq.' . urlencode($masked) . '&select=id,verified_at',
        ];

        $last = ['ok' => false, 'data' => []];
        foreach ($attempts as $query) {
            $res = $this->db->select('pendencia_de_cadastro', $query);
            $last = $res;
            if ($res['ok'] && !empty($res['data'])) {
                return $res;
            }
        }

        return $last;
    }

    private function formatCpf(string $cpfDigits): string
    {
        if (strlen($cpfDigits) !== 11) {
            return $cpfDigits;
        }

        return substr($cpfDigits, 0, 3) . '.'
            . substr($cpfDigits, 3, 3) . '.'
            . substr($cpfDigits, 6, 3) . '-'
            . substr($cpfDigits, 9, 2);
    }
}
