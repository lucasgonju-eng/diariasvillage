<?php

namespace App;

class AdminAuth
{
    public const ROLE_ADMIN = 'admin_principal';
    public const ROLE_SECRETARIA = 'secretaria';

    private const LEGACY_SECRETARIA_PASSWORD = 'Ei32743176';
    private const DEFAULT_SESSION_TTL = 28800;

    private SupabaseClient $db;

    public function __construct(?SupabaseClient $db = null)
    {
        $this->db = $db ?? new SupabaseClient(new HttpClient());
    }

    /**
     * Cria as contas administrativas iniciais a partir dos segredos configurados.
     * Contas existentes não são sobrescritas silenciosamente.
     */
    public function bootstrapFromEnvironment(): void
    {
        $this->ensureConfiguredUser('admin', self::ROLE_ADMIN, (string) Env::get('ADMIN_SECRET', ''));
        $this->ensureConfiguredUser('secretaria', self::ROLE_SECRETARIA, (string) Env::get('SECRETARIA_SECRET', ''));
    }

    public function login(string $username, string $password): array
    {
        $username = strtolower(trim($username));
        if (!in_array($username, ['admin', 'secretaria'], true) || $password === '') {
            $this->audit(null, $username !== '' ? $username : 'desconhecido', 'desconhecido', 'login', false, [
                'reason' => 'invalid_credentials',
            ]);
            return ['ok' => false, 'error' => 'Usuário ou senha inválidos.'];
        }

        $expectedRole = $username === 'admin' ? self::ROLE_ADMIN : self::ROLE_SECRETARIA;
        $configuredSecret = $username === 'admin'
            ? (string) Env::get('ADMIN_SECRET', '')
            : (string) Env::get('SECRETARIA_SECRET', '');
        $legacySecretariaAllowed = $username === 'secretaria'
            && $configuredSecret === ''
            && hash_equals(self::LEGACY_SECRETARIA_PASSWORD, $password);

        $user = $this->findByUsername($username);
        if ($user === null && $configuredSecret !== '' && hash_equals($configuredSecret, $password)) {
            $user = $this->createUser($username, $expectedRole, $password);
        } elseif ($user === null && $legacySecretariaAllowed) {
            $this->logLegacySecretariaWarning();
            $user = $this->createUser($username, $expectedRole, $password);
        }

        if ($user === null || !($user['active'] ?? false)) {
            $this->audit(
                isset($user['id']) ? (string) $user['id'] : null,
                $username,
                (string) ($user['role'] ?? $expectedRole),
                'login',
                false,
                ['reason' => $user === null ? 'invalid_credentials' : 'inactive']
            );
            return ['ok' => false, 'error' => 'Usuário ou senha inválidos.'];
        }

        $passwordValid = password_verify($password, (string) ($user['password_hash'] ?? ''));
        if (!$passwordValid && $configuredSecret !== '' && hash_equals($configuredSecret, $password)) {
            $user = $this->migratePassword($user, $password);
            $passwordValid = $user !== null;
        } elseif (!$passwordValid && $legacySecretariaAllowed) {
            $this->logLegacySecretariaWarning();
            $user = $this->migratePassword($user, $password);
            $passwordValid = $user !== null;
        } elseif ($passwordValid && $legacySecretariaAllowed) {
            $this->logLegacySecretariaWarning();
        }

        if (!$passwordValid || $user === null) {
            $this->audit(
                isset($user['id']) ? (string) $user['id'] : null,
                $username,
                (string) ($user['role'] ?? $expectedRole),
                'login',
                false,
                ['reason' => 'invalid_credentials']
            );
            return ['ok' => false, 'error' => 'Usuário ou senha inválidos.'];
        }

        $role = (string) ($user['role'] ?? '');
        if (!in_array($role, [self::ROLE_ADMIN, self::ROLE_SECRETARIA], true)) {
            $this->audit((string) $user['id'], $username, $role, 'login', false, ['reason' => 'invalid_role']);
            return ['ok' => false, 'error' => 'Conta administrativa inválida.'];
        }

        session_regenerate_id(true);
        $now = time();
        $ttl = $this->sessionTtl();
        $_SESSION['admin_id'] = (string) $user['id'];
        $_SESSION['admin_role'] = $role;
        $_SESSION['admin_session_version'] = (int) ($user['session_version'] ?? 1);
        $_SESSION['admin_issued_at'] = $now;
        $_SESSION['admin_expires_at'] = $now + $ttl;

        // Compatibilidade temporária até todos os endpoints adotarem os helpers centrais.
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_user'] = $role === self::ROLE_ADMIN ? 'admin' : 'secretaria';

        $this->db->update(
            'admin_users',
            'id=eq.' . urlencode((string) $user['id']),
            ['last_login_at' => date('c'), 'updated_at' => date('c')]
        );
        $this->audit((string) $user['id'], $username, $role, 'login', true);

        return ['ok' => true, 'admin' => $this->sessionPayload($user)];
    }

    public function currentSession(): ?array
    {
        $adminId = (string) ($_SESSION['admin_id'] ?? '');
        $role = (string) ($_SESSION['admin_role'] ?? '');
        $sessionVersion = (int) ($_SESSION['admin_session_version'] ?? 0);
        $expiresAt = (int) ($_SESSION['admin_expires_at'] ?? 0);

        if (
            $adminId === ''
            || !in_array($role, [self::ROLE_ADMIN, self::ROLE_SECRETARIA], true)
            || $sessionVersion < 1
            || $expiresAt <= time()
        ) {
            $this->clearSession();
            return null;
        }

        $user = $this->findById($adminId);
        if (
            $user === null
            || !($user['active'] ?? false)
            || (string) ($user['role'] ?? '') !== $role
            || (int) ($user['session_version'] ?? 0) !== $sessionVersion
        ) {
            $this->clearSession();
            return null;
        }

        return $this->sessionPayload($user);
    }

    public function logout(): void
    {
        $this->clearSession();
        session_regenerate_id(true);
    }

    public function audit(
        ?string $adminUserId,
        string $username,
        string $role,
        string $action,
        bool $success = true,
        array $details = []
    ): void {
        $payload = [
            'admin_user_id' => $adminUserId !== '' ? $adminUserId : null,
            'username' => $username !== '' ? $username : 'desconhecido',
            'role' => $role !== '' ? $role : 'desconhecido',
            'action' => $action,
            'success' => $success,
            'details' => $details,
        ];
        $result = $this->db->insert('admin_audit_log', $payload);
        if (!$result['ok']) {
            error_log('AdminAuth: não foi possível registrar auditoria administrativa.');
        }
    }

    private function ensureConfiguredUser(string $username, string $role, string $secret): void
    {
        if ($secret === '' || $this->findByUsername($username) !== null) {
            return;
        }
        $this->createUser($username, $role, $secret);
    }

    private function createUser(string $username, string $role, string $password): ?array
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            return null;
        }

        $result = $this->db->insert('admin_users', [
            'username' => $username,
            'password_hash' => $hash,
            'role' => $role,
            'active' => true,
            'session_version' => 1,
        ]);
        if ($result['ok'] && !empty($result['data'][0])) {
            return $result['data'][0];
        }

        // Pode haver corrida entre duas primeiras requisições.
        return $this->findByUsername($username);
    }

    private function migratePassword(array $user, string $password): ?array
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            return null;
        }
        $nextVersion = max(1, (int) ($user['session_version'] ?? 1) + 1);
        $result = $this->db->update(
            'admin_users',
            'id=eq.' . urlencode((string) ($user['id'] ?? '')),
            [
                'password_hash' => $hash,
                'session_version' => $nextVersion,
                'updated_at' => date('c'),
            ]
        );
        if (!$result['ok'] || empty($result['data'][0])) {
            return null;
        }
        return $result['data'][0];
    }

    private function findByUsername(string $username): ?array
    {
        $result = $this->db->select(
            'admin_users',
            'select=id,username,password_hash,role,active,session_version'
                . '&username=eq.' . urlencode($username)
                . '&limit=1'
        );
        return $result['ok'] && !empty($result['data'][0]) ? $result['data'][0] : null;
    }

    private function findById(string $id): ?array
    {
        $result = $this->db->select(
            'admin_users',
            'select=id,username,password_hash,role,active,session_version'
                . '&id=eq.' . urlencode($id)
                . '&limit=1'
        );
        return $result['ok'] && !empty($result['data'][0]) ? $result['data'][0] : null;
    }

    private function sessionPayload(array $user): array
    {
        return [
            'id' => (string) ($user['id'] ?? ''),
            'username' => (string) ($user['username'] ?? ''),
            'role' => (string) ($user['role'] ?? ''),
            'session_version' => (int) ($user['session_version'] ?? 0),
            'expires_at' => (int) ($_SESSION['admin_expires_at'] ?? 0),
        ];
    }

    private function sessionTtl(): int
    {
        $configured = (int) Env::get('ADMIN_SESSION_TTL_SECONDS', (string) self::DEFAULT_SESSION_TTL);
        return max(300, min($configured, 86400));
    }

    private function clearSession(): void
    {
        foreach ([
            'admin_id',
            'admin_role',
            'admin_session_version',
            'admin_issued_at',
            'admin_expires_at',
            'admin_authenticated',
            'admin_user',
        ] as $key) {
            unset($_SESSION[$key]);
        }
    }

    private function logLegacySecretariaWarning(): void
    {
        error_log(
            'AVISO DE SEGURANÇA: SECRETARIA_SECRET não configurado; '
            . 'autenticação legada temporária da secretaria foi utilizada.'
        );
    }
}
