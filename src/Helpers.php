<?php

namespace App;

class Helpers
{
    private const DEFAULT_USER_SESSION_TTL = 28800;
    private const DEFAULT_USER_SESSION_IDLE_TTL = 1800;

    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    public static function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
            $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
            self::json([
                'ok' => false,
                'error' => 'Método inválido.',
                'method' => $method,
                'content_type' => $contentType,
            ], 405);
        }
    }

    public static function requireAuth(bool $allowPendingStudentSelection = false): array
    {
        $session = self::currentUserSession();
        if (!($session['ok'] ?? false)) {
            if (($session['unavailable'] ?? false) === true) {
                self::json(['ok' => false, 'error' => 'Não foi possível validar a sessão. Tente novamente.'], 503);
            }
            self::json(['ok' => false, 'error' => 'Não autenticado.'], 401);
        }
        $selectionConfirmed = ($_SESSION['family_student_selection_confirmed'] ?? false) === true;
        if (!$allowPendingStudentSelection && !$selectionConfirmed) {
            self::json([
                'ok' => false,
                'code' => 'STUDENT_SELECTION_REQUIRED',
                'error' => 'Escolha o filho antes de continuar.',
                'redirect' => '/selecionar-aluno.php',
            ], 409);
        }

        return $session['user'];
    }

    public static function requireAuthWeb(bool $allowPendingStudentSelection = false): array
    {
        $session = self::currentUserSession();
        if (!($session['ok'] ?? false)) {
            if (($session['unavailable'] ?? false) === true) {
                http_response_code(503);
                header('Retry-After: 5');
                exit('Não foi possível validar a sessão. Tente novamente.');
            }
            header('Location: /login.php');
            exit;
        }
        $selectionConfirmed = ($_SESSION['family_student_selection_confirmed'] ?? false) === true;
        if (!$allowPendingStudentSelection && !$selectionConfirmed) {
            header('Location: /selecionar-aluno.php');
            exit;
        }

        return $session['user'];
    }

    public static function establishUserSession(array $user, bool $resetLifetime = true): void
    {
        $now = time();
        $version = (int) ($user['account_session_version'] ?? 0);
        if ($version < 1) {
            self::clearUserSession();
            throw new \RuntimeException('Versão persistida da sessão do responsável ausente.');
        }
        $_SESSION['user'] = $user;
        $_SESSION['user_session_version'] = $version;
        if ($resetLifetime || empty($_SESSION['user_session_issued_at'])) {
            $_SESSION['user_session_issued_at'] = $now;
            $_SESSION['user_session_expires_at'] = $now + self::userSessionTtl();
        }
        $_SESSION['user_session_last_activity_at'] = $now;
        $_SESSION['user_session_idle_expires_at'] = $now + self::userSessionIdleTtl();
        session_regenerate_id(true);
    }

    public static function clearUserSession(): void
    {
        foreach ([
            'user',
            'user_session_version',
            'user_session_issued_at',
            'user_session_expires_at',
            'user_session_last_activity_at',
            'user_session_idle_expires_at',
            'family_student_selection_required',
            'family_student_selection_confirmed',
            'family_student_count',
            'family_student_selected_at',
            'family_selection_csrf',
            'family_link_request_csrf',
            'profile_csrf_token',
            'financeiro_csrf_token',
        ] as $key) {
            unset($_SESSION[$key]);
        }
    }

    public static function requireAdmin(?AdminAuth $auth = null): array
    {
        $admin = ($auth ?? new AdminAuth())->currentSession();
        if ($admin === null) {
            self::json(['ok' => false, 'error' => 'Não autenticado.'], 401);
        }
        self::requireAdminCsrfForMutation();
        return $admin;
    }

    /**
     * @param string|string[] $roles
     */
    public static function requireAdminRole(string|array $roles, ?AdminAuth $auth = null): array
    {
        $admin = self::requireAdmin($auth);
        $allowedRoles = is_array($roles) ? $roles : [$roles];
        if (!in_array((string) ($admin['role'] ?? ''), $allowedRoles, true)) {
            self::json(['ok' => false, 'error' => 'Acesso negado.'], 403);
        }
        return $admin;
    }

    public static function requireAdminWeb(?AdminAuth $auth = null): array
    {
        $admin = ($auth ?? new AdminAuth())->currentSession();
        if ($admin === null) {
            header('Location: /admin/');
            exit;
        }
        return $admin;
    }

    public static function adminCsrfToken(): string
    {
        $token = trim((string) ($_SESSION['admin_csrf_token'] ?? ''));
        if ($token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION['admin_csrf_token'] = $token;
        }
        return $token;
    }

    /**
     * @param string|string[] $roles
     */
    public static function requireAdminRoleWeb(string|array $roles, ?AdminAuth $auth = null): array
    {
        $admin = self::requireAdminWeb($auth);
        $allowedRoles = is_array($roles) ? $roles : [$roles];
        if (!in_array((string) ($admin['role'] ?? ''), $allowedRoles, true)) {
            http_response_code(403);
            echo 'Acesso negado.';
            exit;
        }
        return $admin;
    }

    public static function baseUrl(): string
    {
        return rtrim(Env::get('APP_URL', ''), '/');
    }

    public static function randomCode(int $length = 8): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }

    public static function randomNumericCode(int $length = 6): string
    {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= (string) random_int(0, 9);
        }
        return $code;
    }

    /**
     * @return array{ok: bool, user?: array, unavailable?: bool}
     */
    public static function currentUserSession(): array
    {
        $storedUser = $_SESSION['user'] ?? null;
        if (!is_array($storedUser)) {
            return ['ok' => false];
        }

        $now = time();
        $expiresAt = (int) ($_SESSION['user_session_expires_at'] ?? 0);
        $idleExpiresAt = (int) ($_SESSION['user_session_idle_expires_at'] ?? 0);
        $storedVersion = (int) ($_SESSION['user_session_version'] ?? 0);
        if (
            $expiresAt <= 0
            || $idleExpiresAt <= 0
            || $storedVersion < 1
            || empty($_SESSION['user_session_issued_at'])
        ) {
            self::clearUserSession();
            return ['ok' => false];
        }
        if (
            $expiresAt <= $now
            || $idleExpiresAt <= $now
        ) {
            self::clearUserSession();
            return ['ok' => false];
        }

        $guardianId = trim((string) ($storedUser['id'] ?? ''));
        $studentId = trim((string) ($storedUser['student_id'] ?? ''));
        if (
            !preg_match('/^[0-9a-f-]{36}$/i', $guardianId)
            || !preg_match('/^[0-9a-f-]{36}$/i', $studentId)
        ) {
            self::clearUserSession();
            return ['ok' => false];
        }

        $client = new SupabaseClient(new HttpClient());
        $result = $client->select(
            'guardians',
            'select=*&id=eq.' . rawurlencode($guardianId) . '&limit=1'
        );
        if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
            return ['ok' => false, 'unavailable' => true];
        }
        $freshUser = $result['data'][0] ?? null;
        if (!is_array($freshUser)) {
            self::clearUserSession();
            return ['ok' => false];
        }

        $freshStudentId = trim((string) ($freshUser['student_id'] ?? ''));
        $storedAuthUserId = trim((string) ($storedUser['auth_user_id'] ?? ''));
        $freshAuthUserId = trim((string) ($freshUser['auth_user_id'] ?? ''));
        $freshVersion = (int) ($freshUser['account_session_version'] ?? 0);
        if (
            $freshStudentId === ''
            || !hash_equals($studentId, $freshStudentId)
            || $storedAuthUserId !== $freshAuthUserId
            || $freshVersion < 1
            || $storedVersion !== $freshVersion
        ) {
            self::clearUserSession();
            return ['ok' => false];
        }

        $_SESSION['user'] = $freshUser;
        $_SESSION['user_session_version'] = $freshVersion;
        $_SESSION['user_session_last_activity_at'] = $now;
        $_SESSION['user_session_idle_expires_at'] = $now + self::userSessionIdleTtl();

        return ['ok' => true, 'user' => $freshUser];
    }

    private static function userSessionTtl(): int
    {
        $configured = (int) Env::get('USER_SESSION_TTL_SECONDS', (string) self::DEFAULT_USER_SESSION_TTL);
        return max(900, min($configured, 86400));
    }

    private static function userSessionIdleTtl(): int
    {
        $configured = (int) Env::get(
            'USER_SESSION_IDLE_TTL_SECONDS',
            (string) self::DEFAULT_USER_SESSION_IDLE_TTL
        );
        return max(300, min($configured, self::userSessionTtl()));
    }

    private static function requireAdminCsrfForMutation(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        $expected = trim((string) ($_SESSION['admin_csrf_token'] ?? ''));
        $provided = trim((string) (
            $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_POST['csrf_token']
            ?? ''
        ));
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            self::json([
                'ok' => false,
                'error' => 'Sessão administrativa expirada. Recarregue a página.',
            ], 403);
        }
    }

    /**
     * Regra comercial de precificação do Day Use:
     * - Até 16/03/2026 (inclusive): sempre R$ 77,00 e tipo planejada.
     * - A partir de 17/03/2026: até 10h no dia => planejada (R$ 77,00); após 10h => emergencial (R$ 97,00).
     * - Datas futuras mantêm planejada (R$ 77,00).
     *
     * @return array{amount: float, daily_type: string}
     */
    public static function resolveDayUseCharge(string $dayUseDate): array
    {
        $timestamp = strtotime($dayUseDate);
        if ($timestamp === false) {
            return ['amount' => 77.00, 'daily_type' => 'planejada'];
        }
        $dayUseIso = date('Y-m-d', $timestamp);
        $tz = new \DateTimeZone('America/Sao_Paulo');
        $now = new \DateTimeImmutable('now', $tz);
        $today = $now->format('Y-m-d');
        $hour = (int) $now->format('H');
        $promoDeadline = '2026-03-16';

        if ($dayUseIso <= $promoDeadline) {
            return ['amount' => 77.00, 'daily_type' => 'planejada'];
        }

        if ($dayUseIso > $today) {
            return ['amount' => 77.00, 'daily_type' => 'planejada'];
        }

        if ($dayUseIso === $today && $hour < 10) {
            return ['amount' => 77.00, 'daily_type' => 'planejada'];
        }

        return ['amount' => 97.00, 'daily_type' => 'emergencial'];
    }
}
