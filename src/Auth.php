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

        $emailsTentados = [];
        foreach ($result['data'] as $userRow) {
            $email = trim((string) ($userRow['email'] ?? ''));
            if ($email === '') {
                continue;
            }
            $emailLower = strtolower($email);
            if (isset($emailsTentados[$emailLower])) {
                continue;
            }
            $emailsTentados[$emailLower] = true;

            $isPlaceholder = str_contains($emailLower, '@placeholder.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $isPlaceholder) {
                continue;
            }

            $signIn = $this->supabaseAuth->signIn($email, $password);
            if ($signIn['ok'] && !empty($signIn['data'])) {
                return ['ok' => true, 'user' => $userRow];
            }
            $err = ($signIn['data'] ?? [])['error_description'] ?? ($signIn['error'] ?? '');
            if (stripos((string) $err, 'email') !== false && stripos((string) $err, 'confirm') !== false) {
                return ['ok' => false, 'error' => 'E-mail ainda não verificado.'];
            }
        }

        $hasPassword = false;
        foreach ($result['data'] as $user) {
            if (!($user['password_hash'] ?? null)) {
                continue;
            }
            $hasPassword = true;
            if (!password_verify($password, $user['password_hash'])) {
                continue;
            }
            if (!$user['verified_at']) {
                return ['ok' => false, 'error' => 'E-mail ainda não verificado.'];
            }
            return ['ok' => true, 'user' => $user];
        }

        if (!$hasPassword) {
            return ['ok' => false, 'error' => 'Cadastro sem senha. Faça o primeiro acesso para criar.'];
        }

        $pendencia = $this->buscarPendenciaPorCpf($cpfDigits);
        if ($pendencia['ok'] && !empty($pendencia['data'])) {
            return [
                'ok' => false,
                'error' => 'Cadastro pendente. A secretaria vai concluir e avisar por e-mail.',
            ];
        }

        return ['ok' => false, 'error' => 'Credenciais inválidas.'];
    }

    private function buscarGuardiansPorCpf(string $cpfDigits): array
    {
        $masked = $this->formatCpf($cpfDigits);

        $attempts = [
            'parent_document=eq.' . urlencode($cpfDigits) . '&select=*',
            'parent_document=eq.' . urlencode($masked) . '&select=*',
            // Fallback para bases antigas com formatação inesperada.
            'parent_document=ilike.' . urlencode('*' . $cpfDigits . '*') . '&select=*',
        ];

        $last = ['ok' => false, 'data' => []];
        foreach ($attempts as $query) {
            $res = $this->db->select('guardians', $query);
            $last = $res;
            if ($res['ok'] && !empty($res['data'])) {
                return $res;
            }
        }

        return $last;
    }

    private function buscarPendenciaPorCpf(string $cpfDigits): array
    {
        $masked = $this->formatCpf($cpfDigits);

        $attempts = [
            'guardian_cpf=eq.' . urlencode($cpfDigits) . '&select=id,verified_at',
            'guardian_cpf=eq.' . urlencode($masked) . '&select=id,verified_at',
            'guardian_cpf=ilike.' . urlencode('*' . $cpfDigits . '*') . '&select=id,verified_at',
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
