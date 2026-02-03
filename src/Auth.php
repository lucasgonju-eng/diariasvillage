<?php

namespace App;

class Auth
{
    private SupabaseClient $db;

    public function __construct(SupabaseClient $db)
    {
        $this->db = $db;
    }

    public function login(string $email, string $password): array
    {
        $result = $this->db->select('guardians', 'email=eq.' . urlencode($email) . '&select=*');
        if (!$result['ok'] || empty($result['data'])) {
            return ['ok' => false, 'error' => 'Credenciais invalidas.'];
        }

        $user = $result['data'][0];
        if (!$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'Credenciais invalidas.'];
        }

        if (!$user['verified_at']) {
            return ['ok' => false, 'error' => 'E-mail ainda nao verificado.'];
        }

        return ['ok' => true, 'user' => $user];
    }
}
