<?php

namespace App;

class AdminAuth
{
    public const ROLE_ADMIN = 'admin_principal';
    public const ROLE_SECRETARIA = 'secretaria';

    private const DEFAULT_SESSION_TTL = 28800;

    private SupabaseClient $db;

    public function __construct(?SupabaseClient $db = null)
    {
        $this->db = $db ?? new SupabaseClient(new HttpClient());
    }

    /**
     * Cria a conta inicial do admin principal a partir do segredo configurado.
     * Contas existentes não são sobrescritas silenciosamente.
     */
    public function bootstrapFromEnvironment(): void
    {
        $this->ensureConfiguredUser('admin', self::ROLE_ADMIN, (string) Env::get('ADMIN_SECRET', ''));
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
            : '';
        $lookup = $this->findByUsernameResult($username);
        if (!($lookup['ok'] ?? false)) {
            $this->audit(null, $username, $expectedRole, 'login', false, [
                'reason' => 'identity_lookup_failed',
            ]);
            return ['ok' => false, 'error' => 'Não foi possível validar o acesso agora.'];
        }
        $user = is_array($lookup['user'] ?? null) ? $lookup['user'] : null;

        if ($user === null && $configuredSecret !== '' && hash_equals($configuredSecret, $password)) {
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

        $passwordValid = !($user['requires_password_setup'] ?? false)
            && password_verify($password, (string) ($user['password_hash'] ?? ''));
        if (!$passwordValid && $configuredSecret !== '' && hash_equals($configuredSecret, $password)) {
            $user = $this->migratePassword($user, $password);
            $passwordValid = $user !== null;
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

    /**
     * Define a credencial operacional da secretaria sem persistir a senha em
     * código, ambiente, sessão ou auditoria.
     *
     * @return array{ok: bool, error?: string, created?: bool, session_version?: int}
     */
    public function configureSecretariaPassword(array $actor, string $password): array
    {
        $actorId = trim((string) ($actor['id'] ?? ''));
        $actorUsername = trim((string) ($actor['username'] ?? ''));
        $actorRole = trim((string) ($actor['role'] ?? ''));
        if (
            $actorId === ''
            || $actorUsername === ''
            || $actorRole !== self::ROLE_ADMIN
        ) {
            return ['ok' => false, 'error' => 'Acesso negado.'];
        }

        $passwordLength = function_exists('mb_strlen') ? mb_strlen($password) : strlen($password);
        $strongEnough = $passwordLength >= 12
            && $passwordLength <= 128
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^a-zA-Z0-9]/', $password) === 1;
        if (!$strongEnough) {
            return [
                'ok' => false,
                'error' => 'Use ao menos 12 caracteres, com maiúscula, minúscula, número e símbolo.',
            ];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            return ['ok' => false, 'error' => 'Não foi possível proteger a nova senha.'];
        }

        $result = $this->db->rpc('configure_secretaria_credentials', [
            'p_actor_id' => $actorId,
            'p_actor_session_version' => (int) ($actor['session_version'] ?? 0),
            'p_password_hash' => $hash,
        ]);
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        if (!($result['ok'] ?? false) || !($data['ok'] ?? false)) {
            return ['ok' => false, 'error' => 'Não foi possível salvar o acesso da secretaria.'];
        }

        return [
            'ok' => true,
            'created' => (bool) ($data['created'] ?? false),
            'session_version' => (int) ($data['user']['session_version'] ?? 1),
        ];
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
            || ($user['requires_password_setup'] ?? false)
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
        $result = $this->findByUsernameResult($username);
        return ($result['ok'] ?? false) && is_array($result['user'] ?? null)
            ? $result['user']
            : null;
    }

    /**
     * @return array{ok: bool, user: array<string, mixed>|null}
     */
    private function findByUsernameResult(string $username): array
    {
        $result = $this->db->select(
            'admin_users',
            'select=id,username,password_hash,role,active,session_version,requires_password_setup'
                . '&username=eq.' . urlencode($username)
                . '&limit=1'
        );
        if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
            return ['ok' => false, 'user' => null];
        }
        return [
            'ok' => true,
            'user' => is_array($result['data'][0] ?? null) ? $result['data'][0] : null,
        ];
    }

    private function findById(string $id): ?array
    {
        $result = $this->db->select(
            'admin_users',
            'select=id,username,password_hash,role,active,session_version,requires_password_setup'
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

}
