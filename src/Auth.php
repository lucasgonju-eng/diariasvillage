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

        return ['ok' => false, 'error' => 'Credenciais inválidas.'];
    }
}
