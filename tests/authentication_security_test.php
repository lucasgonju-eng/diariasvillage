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
    public bool $failNextSelect = false;

    public function __construct()
    {
    }

    public function select(string $table, string $query = ''): array
    {
        if ($this->failNextSelect) {
            $this->failNextSelect = false;
            return ['ok' => false, 'data' => []];
        }
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

    public function rpc(string $functionName, array $payload = []): array
    {
        if ($functionName === 'claim_legacy_secretaria_bridge') {
            if (isset($this->users['secretaria'])) {
                return [
                    'ok' => true,
                    'data' => ['ok' => false, 'code' => 'SECRETARIA_ALREADY_EXISTS'],
                ];
            }
            $user = [
                'id' => sprintf('00000000-0000-4000-8000-%012d', count($this->users) + 1),
                'username' => 'secretaria',
                'password_hash' => (string) ($payload['p_password_hash'] ?? ''),
                'role' => AdminAuth::ROLE_SECRETARIA,
                'active' => true,
                'session_version' => 1,
                'requires_password_setup' => true,
            ];
            $this->users['secretaria'] = $user;
            $this->audit[] = [
                'action' => 'legacy_secretaria_bridge_claimed',
                'details' => ['requires_password_setup' => true],
            ];
            $publicUser = $user;
            unset($publicUser['password_hash']);
            return ['ok' => true, 'data' => ['ok' => true, 'user' => $publicUser]];
        }

        if ($functionName === 'configure_secretaria_credentials') {
            $actor = null;
            foreach ($this->users as $candidate) {
                if ((string) ($candidate['id'] ?? '') === (string) ($payload['p_actor_id'] ?? '')) {
                    $actor = $candidate;
                    break;
                }
            }
            if (
                !is_array($actor)
                || ($actor['role'] ?? '') !== AdminAuth::ROLE_ADMIN
                || !($actor['active'] ?? false)
                || (int) ($actor['session_version'] ?? 0)
                    !== (int) ($payload['p_actor_session_version'] ?? 0)
            ) {
                return ['ok' => true, 'data' => ['ok' => false, 'code' => 'ADMIN_NOT_AUTHORIZED']];
            }

            $existing = $this->users['secretaria'] ?? null;
            $created = !is_array($existing);
            $user = is_array($existing) ? $existing : [
                'id' => sprintf('00000000-0000-4000-8000-%012d', count($this->users) + 1),
                'username' => 'secretaria',
                'session_version' => 0,
            ];
            $user = array_merge($user, [
                'password_hash' => (string) ($payload['p_password_hash'] ?? ''),
                'role' => AdminAuth::ROLE_SECRETARIA,
                'active' => true,
                'session_version' => $created ? 1 : ((int) $user['session_version'] + 1),
                'requires_password_setup' => false,
            ]);
            $this->users['secretaria'] = $user;
            $this->audit[] = [
                'admin_user_id' => $actor['id'],
                'username' => $actor['username'],
                'role' => $actor['role'],
                'action' => 'configure_secretaria_access',
                'success' => true,
                'details' => ['created' => $created],
            ];
            $publicUser = $user;
            unset($publicUser['password_hash']);
            return [
                'ok' => true,
                'data' => ['ok' => true, 'created' => $created, 'user' => $publicUser],
            ];
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
$familyLinkMigration = $read($root . '/supabase/migrations/20260903051032_secure_family_link_requests.sql');
$selectStudent = $read($root . '/public/api/select-student.php');
$userDashboard = $read($root . '/public/dashboard.php');
$attendance = $read($root . '/public/api/admin-attendance.php');
$secretariaAccess = $read($root . '/public/api/admin-secretaria-access.php');
$adminDashboard = $read($root . '/public/admin/dashboard.php');
$adminDashboardJs = $read($root . '/public/assets/js/admin-dashboard.js');
$secretariaMigration = $read(
    $root . '/supabase/migrations/20260903143844_secure_secretaria_credential_setup.sql'
);

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
$assertContains('Admin configura secretaria sem ambiente', $adminAuthSource, 'function configureSecretariaPassword(');
$assertNotContains(
    'bootstrap não usa mais segredo da secretaria no ambiente',
    $adminAuthSource,
    "ensureConfiguredUser('secretaria'"
);
$assertContains(
    'fallback só pode iniciar conta ausente',
    $adminAuthSource,
    '&& $user === null'
);
$assertContains(
    'fallback não usa segredo de ambiente',
    $adminAuthSource,
    "&& \$configuredSecret === ''"
);
$assertContains(
    'fallback usa claim atômico de uso único',
    $adminAuthSource,
    "rpc('claim_legacy_secretaria_bridge'"
);
$assertContains(
    'configuração usa RPC atômica',
    $adminAuthSource,
    "rpc('configure_secretaria_credentials'"
);
$assertContains(
    'conta pendente não aceita senha persistida',
    $adminAuthSource,
    "!(\$user['requires_password_setup'] ?? false)"
);
$assertContains(
    'migration marca ponte pendente',
    $secretariaMigration,
    'requires_password_setup boolean not null default false'
);
$assertContains(
    'migration serializa credencial',
    $secretariaMigration,
    "pg_advisory_xact_lock(hashtextextended('admin_users:secretaria', 0))"
);
$assertContains(
    'RPC valida versão do admin',
    $secretariaMigration,
    'and session_version = p_actor_session_version'
);
$assertContains(
    'trigger protege rollout com código antigo',
    $secretariaMigration,
    'create trigger trg_secretaria_password_setup_origin'
);
$assertContains(
    'linha legada anterior ao lock também é bloqueada',
    $secretariaMigration,
    "where username = 'secretaria';"
);
$assertContains(
    'RPC marca criação segura explicitamente',
    $secretariaMigration,
    "set_config('app.secretaria_secure_setup', 'confirmed', true)"
);
$assertContains(
    'sessão da ponte é marcada explicitamente',
    $adminAuthSource,
    "\$_SESSION['admin_legacy_bridge_claimed'] = true"
);
$assertContains(
    'sessão antiga sem marca é revogada',
    $adminAuthSource,
    "(\$user['requires_password_setup'] ?? false) && !\$pendingPasswordSetupAllowed"
);
$assertContains('Helper central exige admin', $helpers, 'function requireAdmin(');
$assertContains('Helper central exige role', $helpers, 'function requireAdminRole(');
$assertContains('Sessão usa strict mode', $bootstrap, "ini_set('session.use_strict_mode', '1')");
$assertContains('Cookie é Secure', $bootstrap, "'secure' => true");
$assertContains('Cookie é HttpOnly', $bootstrap, "'httponly' => true");
$assertContains('Cookie define SameSite', $bootstrap, "'samesite' => 'Lax'");
$assertOrder('cookie antes de session_start', $bootstrap, 'session_set_cookie_params(', 'session_start()');
$assertContains('mobile encaminha ao fluxo canônico', $mobile, "header('Location: /primeiro-acesso.php?origem=mobile')");
$assertContains('e-mail fica apenas no vínculo principal', $firstAccessEmailFix, 'where id = p_primary_guardian_id');
$assertContains('migration final limita primeiro acesso ao vínculo confirmado', $familyLinkMigration, 'where id = p_guardian_id');
$assertContains('irmãos exigem fluxo separado após primeiro acesso', $familyLinkMigration, "'related_guardians_updated', 0");
$assertContains('troca de filho exige a mesma conta Auth', $selectStudent, '&auth_user_id=eq.');
$assertContains('troca de filho exige vínculo com aluno', $selectStudent, '&student_id=eq.');
$assertNotContains('troca de filho não usa CPF como autorização', $selectStudent, 'parent_document');
$assertContains('dashboard lista filhos pela conta Auth', $userDashboard, '&auth_user_id=eq.');

$closeDayStart = strpos($attendance, "if (\$action === 'close_day')");
$editStart = strpos($attendance, "if (\$action === 'edit')");
$closeDayBlock = (
    $closeDayStart !== false
    && $editStart !== false
    && $editStart > $closeDayStart
) ? substr($attendance, $closeDayStart, $editStart - $closeDayStart) : '';
$assertContains(
    'chamada permite secretaria e admin',
    $attendance,
    'Helpers::requireAdminRole([\App\AdminAuth::ROLE_ADMIN, \App\AdminAuth::ROLE_SECRETARIA])'
);
$assertContains(
    'presença da secretaria fica em revisão',
    $closeDayBlock,
    "'status' => AttendanceCalls::STATUS_EM_REVISAO"
);
$assertNotContains(
    'fechamento da chamada não cria cobrança',
    $closeDayBlock,
    '$asaas->createPayment('
);
$assertOrder(
    'aprovação humana antecede criação da cobrança',
    $attendance,
    'Apenas admin pode autorizar chamada.',
    '$asaas->createPayment('
);
$assertContains(
    'somente admin rejeita chamada',
    $attendance,
    "Helpers::json(['ok' => false, 'error' => 'Apenas admin pode rejeitar chamada.'], 403)"
);
$assertContains(
    'mensalista permanece sem cobrança na chamada',
    $attendance,
    "'blocked_reason' => 'monthly_covered'"
);

$adminOnlyEndpoints = [
    'admin-charge.php',
    'admin-cashflow.php',
    'admin-delete-payment.php',
    'admin-reset-password.php',
    'admin-sync-recebidas.php',
    'admin-sync-charges-payments.php',
    'admin-view-as-user.php',
    'admin-secretaria-access.php',
];
foreach ($adminOnlyEndpoints as $endpoint) {
    $source = $read($root . '/public/api/' . $endpoint);
    $assertContains(
        'RBAC de admin principal em ' . $endpoint,
        $source,
        'AdminAuth::ROLE_ADMIN'
    );
}

$assertContains('endpoint da secretaria exige POST', $secretariaAccess, 'Helpers::requirePost()');
$assertContains(
    'endpoint da secretaria exige confirmação',
    $secretariaAccess,
    'hash_equals($password, $confirmation)'
);
$assertNotContains('endpoint não registra senha', $secretariaAccess, 'error_log(');
$assertContains('painel mostra acesso da secretaria', $adminDashboard, 'tab-acesso-secretaria');
$assertContains('painel limita acesso ao admin principal', $adminDashboard, '<?php if ($isAdminPrincipal): ?>');
$assertContains(
    'JavaScript envia senha somente por POST',
    $adminDashboardJs,
    "fetch('/api/admin-secretaria-access.php'"
);

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

if (!isset($fakeDb->users['admin']) || isset($fakeDb->users['secretaria'])) {
    $failures[] = 'Bootstrap deve criar somente o admin; secretaria é configurada pelo painel.';
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

$adminActor = $login['admin'] ?? [];
$weakSecretaria = $adminAuth->configureSecretariaPassword($adminActor, 'senha-fraca');
if ($weakSecretaria['ok'] ?? false) {
    $failures[] = 'Senha fraca da secretaria deveria ser recusada.';
}
$unauthorizedSecretaria = $adminAuth->configureSecretariaPassword([
    'id' => '00000000-0000-4000-8000-000000000099',
    'username' => 'secretaria',
    'role' => AdminAuth::ROLE_SECRETARIA,
], 'Secretaria#2026Nova');
if ($unauthorizedSecretaria['ok'] ?? false) {
    $failures[] = 'Secretaria não pode configurar a própria credencial administrativa.';
}

$secretariaPassword = 'Secretaria#2026Nova';
$configuredSecretaria = $adminAuth->configureSecretariaPassword($adminActor, $secretariaPassword);
if (!($configuredSecretaria['ok'] ?? false) || !isset($fakeDb->users['secretaria'])) {
    $failures[] = 'Admin principal deveria criar a conta da secretaria.';
} elseif (
    ($fakeDb->users['secretaria']['password_hash'] ?? '') === $secretariaPassword
    || !password_verify($secretariaPassword, (string) $fakeDb->users['secretaria']['password_hash'])
) {
    $failures[] = 'Senha da secretaria deve existir somente como hash verificável.';
}

$secretariaLogin = $adminAuth->login('secretaria', $secretariaPassword);
if (!($secretariaLogin['ok'] ?? false)) {
    $failures[] = 'Nova credencial da secretaria deveria autenticar.';
}
$_SESSION = [];
$legacyLoginAfterConfiguration = $adminAuth->login('secretaria', 'Ei32743176');
if ($legacyLoginAfterConfiguration['ok'] ?? false) {
    $failures[] = 'Senha legada deve falhar depois da configuração segura.';
}

$versionBeforeRotation = (int) ($fakeDb->users['secretaria']['session_version'] ?? 0);
$rotatedSecretaria = $adminAuth->configureSecretariaPassword($adminActor, 'Secretaria#2026Rotacionada');
if (
    !($rotatedSecretaria['ok'] ?? false)
    || (int) ($fakeDb->users['secretaria']['session_version'] ?? 0) <= $versionBeforeRotation
) {
    $failures[] = 'Troca da senha da secretaria deveria revogar sessões anteriores.';
}
if (
    !array_filter(
        $fakeDb->audit,
        static fn(array $entry): bool => ($entry['action'] ?? '') === 'configure_secretaria_access'
    )
) {
    $failures[] = 'Configuração da secretaria deveria gerar auditoria.';
}
if (str_contains(json_encode($fakeDb->audit) ?: '', $secretariaPassword)) {
    $failures[] = 'Auditoria não pode conter a senha da secretaria.';
}

$bridgeDb = new InMemoryAdminSupabaseClient();
$bridgeAuth = new AdminAuth($bridgeDb);
$_SESSION = [];
$firstLegacyLogin = $bridgeAuth->login('secretaria', 'Ei32743176');
if (!($firstLegacyLogin['ok'] ?? false)) {
    $failures[] = 'Ponte legada deveria permitir no máximo o primeiro login.';
}
$bridgeUser = $bridgeDb->users['secretaria'] ?? [];
if (
    !($bridgeUser['requires_password_setup'] ?? false)
    || password_verify('Ei32743176', (string) ($bridgeUser['password_hash'] ?? ''))
) {
    $failures[] = 'Ponte legada deve persistir hash aleatório e exigir nova senha.';
}
if ($bridgeAuth->currentSession() === null) {
    $failures[] = 'Sessão explicitamente criada pela ponte deveria permanecer válida.';
}
$_SESSION = [];
$secondLegacyLogin = $bridgeAuth->login('secretaria', 'Ei32743176');
if ($secondLegacyLogin['ok'] ?? false) {
    $failures[] = 'Ponte legada não pode aceitar um segundo login.';
}

$bridgeUserId = (string) ($bridgeUser['id'] ?? '');
$_SESSION = [
    'admin_id' => $bridgeUserId,
    'admin_role' => AdminAuth::ROLE_SECRETARIA,
    'admin_session_version' => 1,
    'admin_issued_at' => time(),
    'admin_expires_at' => time() + 600,
    'admin_authenticated' => true,
];
if ($bridgeAuth->currentSession() !== null || isset($_SESSION['admin_authenticated'])) {
    $failures[] = 'Sessão criada pelo código antigo durante rollout deve ser revogada.';
}

$safeHash = password_hash('Secretaria#Segura2026', PASSWORD_DEFAULT);
$readFailureDb = new InMemoryAdminSupabaseClient();
$readFailureDb->users['secretaria'] = [
    'id' => '00000000-0000-4000-8000-000000000777',
    'username' => 'secretaria',
    'password_hash' => $safeHash,
    'role' => AdminAuth::ROLE_SECRETARIA,
    'active' => true,
    'session_version' => 4,
    'requires_password_setup' => false,
];
$readFailureDb->failNextSelect = true;
$readFailureAuth = new AdminAuth($readFailureDb);
$_SESSION = [];
$legacyDuringReadFailure = $readFailureAuth->login('secretaria', 'Ei32743176');
if (
    ($legacyDuringReadFailure['ok'] ?? false)
    || ($readFailureDb->users['secretaria']['password_hash'] ?? '') !== $safeHash
    || (int) ($readFailureDb->users['secretaria']['session_version'] ?? 0) !== 4
) {
    $failures[] = 'Falha de leitura deve bloquear login sem alterar credencial segura.';
}

$_SESSION = [];
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
