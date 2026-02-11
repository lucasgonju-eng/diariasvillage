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
        $result = $this->db->select('guardians', 'parent_document=eq.' . urlencode($cpfDigits) . '&select=*');
        if (!$result['ok'] || empty($result['data'])) {
            $pendencia = $this->db->select(
                'pendencia_de_cadastro',
                'guardian_cpf=eq.' . urlencode($cpfDigits) . '&select=id,verified_at'
            );
            if ($pendencia['ok'] && !empty($pendencia['data'])) {
                return [
                    'ok' => false,
                    'error' => 'Cadastro pendente. A secretaria vai concluir e avisar por e-mail.',
                ];
            }
            return ['ok' => false, 'error' => 'Credenciais inválidas.'];
        }

        $email = trim($result['data'][0]['email'] ?? '');
        $isPlaceholder = str_contains($email, '@placeholder.');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && !$isPlaceholder) {
            $signIn = $this->supabaseAuth->signIn($email, $password);
            if ($signIn['ok'] && !empty($signIn['data'])) {
                return ['ok' => true, 'user' => $result['data'][0]];
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

        $pendencia = $this->db->select(
            'pendencia_de_cadastro',
            'guardian_cpf=eq.' . urlencode($cpfDigits) . '&select=id,verified_at'
        );
        if ($pendencia['ok'] && !empty($pendencia['data'])) {
            return [
                'ok' => false,
                'error' => 'Cadastro pendente. A secretaria vai concluir e avisar por e-mail.',
            ];
        }

        return ['ok' => false, 'error' => 'Credenciais inválidas.'];
    }
}
