<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\AdminAuth;
use App\SupabaseClient;

final class InMemoryAdminSupabaseClient extends SupabaseClient
{
    /** @var array<string, array<string, mixed>> */
    public array $users = [];
    /** @var array<int, array<string, mixed>> */
    public array $audit = [];

    public function __construct()
    {
    }

    public function select(string $table, string $query = ''): array
    {
        if ($table !== 'admin_users') {
            return ['ok' => true, 'data' => []];
        }
        foreach ($this->users as $user) {
            if (
                (preg_match('/username=eq\.([^&]+)/', $query, $match)
                    && (string) $user['username'] === urldecode($match[1]))
                || (preg_match('/id=eq\.([^&]+)/', $query, $match)
                    && (string) $user['id'] === urldecode($match[1]))
            ) {
                return ['ok' => true, 'data' => [$user]];
            }
        }
        return ['ok' => true, 'data' => []];
    }

    public function insert(string $table, array $payload): array
    {
        if ($table === 'admin_audit_log') {
            $this->audit[] = $payload;
            return ['ok' => true, 'data' => [$payload]];
        }
        if ($table !== 'admin_users') {
            return ['ok' => false, 'data' => []];
        }
        $payload['id'] = sprintf('00000000-0000-4000-8000-%012d', count($this->users) + 1);
        $this->users[(string) $payload['username']] = $payload;
        return ['ok' => true, 'data' => [$payload]];
    }

    public function update(string $table, string $query, array $payload): array
    {
        if ($table !== 'admin_users' || !preg_match('/id=eq\.([^&]+)/', $query, $match)) {
            return ['ok' => false, 'data' => []];
        }
        $id = urldecode($match[1]);
        foreach ($this->users as $username => $user) {
            if ((string) $user['id'] !== $id) {
                continue;
            }
            $this->users[$username] = array_merge($user, $payload);
            return ['ok' => true, 'data' => [$this->users[$username]]];
        }
        return ['ok' => false, 'data' => []];
    }
}

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $path) use (&$failures): string {
    $content = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($content)) {
        $failures[] = 'Arquivo ausente ou ilegível: ' . $path;
        return '';
    }
    return $content;
};
$assertContains = static function (string $label, string $content, string $needle) use (&$failures): void {
    if (!str_contains($content, $needle)) {
        $failures[] = $label . ' deveria conter: ' . $needle;
    }
};
$assertNotContains = static function (string $label, string $content, string $needle) use (&$failures): void {
    if (str_contains($content, $needle)) {
        $failures[] = $label . ' não deveria conter: ' . $needle;
    }
};
$assertOrder = static function (string $label, string $content, string $first, string $second) use (&$failures): void {
    $firstAt = strpos($content, $first);
    $secondAt = strpos($content, $second);
    if ($firstAt === false || $secondAt === false || $firstAt >= $secondAt) {
        $failures[] = $label . ' está fora da ordem segura.';
    }
};

$register = $read($root . '/public/api/register-primeiro-acesso.php');
$legacyRegister = $read($root . '/public/api/register.php');
$supabaseAuth = $read($root . '/src/SupabaseAuth.php');
$adminAuthSource = $read($root . '/src/AdminAuth.php');
$helpers = $read($root . '/src/Helpers.php');
$bootstrap = $read($root . '/src/Bootstrap.php');
$mobile = $read($root . '/public/mobile/index.php');
$firstAccessEmailFix = $read($root . '/supabase/migrations/20260903002233_fix_first_access_email_uniqueness.sql');
$selectStudent = $read($root . '/public/api/select-student.php');
$userDashboard = $read($root . '/public/dashboard.php');

$assertContains('primeiro acesso inicia claim', $register, "rpc('begin_first_access_claim'");
$assertContains('primeiro acesso conclui claim', $register, "rpc('complete_first_access_claim'");
$assertContains('primeiro acesso identifica vínculo principal', $register, "'p_primary_guardian_id'");
$assertContains('primeiro acesso cancela claim', $register, "rpc('cancel_first_access_claim'");
$assertContains('primeiro acesso compensa Auth', $register, '$auth->deleteUser($createdAuthUserId)');
$assertContains('primeiro acesso verifica commit incerto', $register, "'FIRST_ACCESS_STATUS_UNKNOWN'");
$assertOrder('claim antes do Auth', $register, "rpc('begin_first_access_claim'", '$auth->createUser(');
$assertOrder('Auth antes da conclusão', $register, '$auth->createUser(', "rpc('complete_first_access_claim'");
$assertNotContains('primeiro acesso não redefine Auth', $register, '$auth->updateUser(');
$assertNotContains('primeiro acesso não procura usuário para redefinir', $register, '$auth->listUsers(');
$assertNotContains('primeiro acesso não atualiza guardians fora da RPC', $register, "\$client->update(\n    'guardians'");
$assertContains('cadastro legado responde 404', $legacyRegister, 'http_response_code(404)');
$assertContains('cadastro legado impede cache', $legacyRegister, "header('Cache-Control: no-store')");
$assertNotContains('cadastro legado não acessa responsáveis', $legacyRegister, 'SupabaseClient');
$assertNotContains('cadastro legado não aceita nome de aluno', $legacyRegister, 'student_name');
$assertNotContains('cadastro legado não cria vínculo', $legacyRegister, "insert('guardians'");
$assertContains('Supabase Auth permite compensação', $supabaseAuth, 'function deleteUser(');

$assertContains('Admin usa password_hash', $adminAuthSource, 'password_hash(');
$assertContains('Admin valida password_hash', $adminAuthSource, 'password_verify(');
$assertContains('Admin regenera sessão', $adminAuthSource, 'session_regenerate_id(true)');
$assertContains('Admin valida versão de sessão', $adminAuthSource, "['admin_session_version']");
$assertContains('Admin registra auditoria', $adminAuthSource, "'admin_audit_log'");
$assertContains('Helper central exige admin', $helpers, 'function requireAdmin(');
$assertContains('Helper central exige role', $helpers, 'function requireAdminRole(');
$assertContains('Sessão usa strict mode', $bootstrap, "ini_set('session.use_strict_mode', '1')");
$assertContains('Cookie é Secure', $bootstrap, "'secure' => true");
$assertContains('Cookie é HttpOnly', $bootstrap, "'httponly' => true");
$assertContains('Cookie define SameSite', $bootstrap, "'samesite' => 'Lax'");
$assertOrder('cookie antes de session_start', $bootstrap, 'session_set_cookie_params(', 'session_start()');
$assertContains('mobile encaminha ao fluxo canônico', $mobile, "header('Location: /primeiro-acesso.php?origem=mobile')");
$assertContains('e-mail fica apenas no vínculo principal', $firstAccessEmailFix, 'where id = p_primary_guardian_id');
$assertContains('demais filhos recebem a mesma conta Auth', $firstAccessEmailFix, 'set auth_user_id = p_auth_user_id');
$assertContains('troca de filho exige a mesma conta Auth', $selectStudent, '&auth_user_id=eq.');
$assertContains('troca de filho exige vínculo com aluno', $selectStudent, '&student_id=eq.');
$assertNotContains('troca de filho não usa CPF como autorização', $selectStudent, 'parent_document');
$assertContains('dashboard lista filhos pela conta Auth', $userDashboard, '&auth_user_id=eq.');

$adminOnlyEndpoints = [
    'admin-charge.php',
    'admin-cashflow.php',
    'admin-delete-payment.php',
    'admin-reset-password.php',
    'admin-sync-recebidas.php',
    'admin-sync-charges-payments.php',
    'admin-view-as-user.php',
];
foreach ($adminOnlyEndpoints as $endpoint) {
    $source = $read($root . '/public/api/' . $endpoint);
    $assertContains(
        'RBAC de admin principal em ' . $endpoint,
        $source,
        'AdminAuth::ROLE_ADMIN'
    );
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = [];
$_ENV['ADMIN_SECRET'] = 'teste-admin-seguro-2026';
$_ENV['SECRETARIA_SECRET'] = 'teste-secretaria-seguro-2026';
$_ENV['ADMIN_SESSION_TTL_SECONDS'] = '600';

$fakeDb = new InMemoryAdminSupabaseClient();
$adminAuth = new AdminAuth($fakeDb);
$adminAuth->bootstrapFromEnvironment();

if (!isset($fakeDb->users['admin'], $fakeDb->users['secretaria'])) {
    $failures[] = 'Bootstrap deveria criar as duas contas configuradas.';
}
if (($fakeDb->users['admin']['password_hash'] ?? '') === $_ENV['ADMIN_SECRET']) {
    $failures[] = 'Senha administrativa não pode ser armazenada em texto puro.';
}

$login = $adminAuth->login('admin', $_ENV['ADMIN_SECRET']);
if (!($login['ok'] ?? false)) {
    $failures[] = 'Login administrativo configurado deveria funcionar.';
}
if (
    empty($_SESSION['admin_id'])
    || ($_SESSION['admin_role'] ?? '') !== AdminAuth::ROLE_ADMIN
    || (int) ($_SESSION['admin_session_version'] ?? 0) < 1
) {
    $failures[] = 'Sessão administrativa deveria conter id, role e versão.';
}
if ($adminAuth->currentSession() === null) {
    $failures[] = 'Sessão administrativa válida deveria ser aceita.';
}

$_SESSION['admin_expires_at'] = time() - 1;
if ($adminAuth->currentSession() !== null || isset($_SESSION['admin_authenticated'])) {
    $failures[] = 'Sessão administrativa expirada deveria ser removida.';
}
if (!array_filter($fakeDb->audit, static fn(array $entry): bool => ($entry['action'] ?? '') === 'login')) {
    $failures[] = 'Login administrativo deveria gerar auditoria.';
}

unset($_ENV['ADMIN_SECRET'], $_ENV['SECRETARIA_SECRET'], $_ENV['ADMIN_SESSION_TTL_SECONDS']);

if ($failures !== []) {
    fwrite(STDERR, "Falhas na autenticação segura:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "OK: primeiro acesso e autenticação administrativa validados.\n";
