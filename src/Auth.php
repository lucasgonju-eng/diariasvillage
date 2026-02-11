<?php

namespace App;

class Auth
{
    private SupabaseClient $db;

    public function __construct(SupabaseClient $db)
    {
        $this->db = $db;
    }

    public function login(string $cpf, string $password): array
    {
        $cpfDigits = preg_replace('/\D+/', '', $cpf) ?? '';
        $result = $this->db->select('guardians', 'parent_document=eq.' . urlencode($cpfDigits) . '&select=*');
        if (!$result['ok'] || empty($result['data'])) {
            return ['ok' => false, 'error' => 'Credenciais invalidas.'];
        }

        foreach ($result['data'] as $user) {
            if (!$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
                continue;
            }
            if (!$user['verified_at']) {
                return ['ok' => false, 'error' => 'E-mail ainda nao verificado.'];
            }
            return ['ok' => true, 'user' => $user];
        }

        return ['ok' => false, 'error' => 'Credenciais invalidas.'];
    }
}
