<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\LoginThrottle;
use App\SupabaseClient;

final class LoginThrottleFakeClient extends SupabaseClient
{
    /** @var array<int, array{function: string, payload: array}> */
    public array $calls = [];
    public bool $allowed = true;

    public function __construct()
    {
    }

    public function rpc(string $functionName, array $payload = []): array
    {
        $this->calls[] = ['function' => $functionName, 'payload' => $payload];
        if ($functionName === 'claim_login_attempt') {
            return [
                'ok' => true,
                'data' => [
                    'ok' => true,
                    'allowed' => $this->allowed,
                    'retry_after' => $this->allowed ? 0 : 300,
                ],
            ];
        }
        if ($functionName === 'clear_login_attempts') {
            return ['ok' => true, 'data' => ['ok' => true]];
        }
        return ['ok' => false, 'data' => null];
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
$contains = static function (string $label, string $content, string $needle) use (&$failures): void {
    if (!str_contains($content, $needle)) {
        $failures[] = $label . ' deveria conter: ' . $needle;
    }
};
$notContains = static function (string $label, string $content, string $needle) use (&$failures): void {
    if (str_contains($content, $needle)) {
        $failures[] = $label . ' não deveria conter: ' . $needle;
    }
};
$order = static function (string $label, string $content, string $first, string $second) use (&$failures): void {
    $firstAt = strpos($content, $first);
    $secondAt = strpos($content, $second);
    if ($firstAt === false || $secondAt === false || $firstAt >= $secondAt) {
        $failures[] = $label . ' está fora da ordem segura.';
    }
};

$migration = $read($root . '/supabase/migrations/20260903121505_secure_login_throttling_and_guardian_sessions.sql');
$hardeningMigration = $read($root . '/supabase/migrations/20260903123854_harden_login_throttle_concurrency.sql');
$purgeMigration = $read($root . '/supabase/migrations/20260903124139_serialize_login_throttle_purge.sql');
$nonBlockingPurgeMigration = $read($root . '/supabase/migrations/20260903124312_make_login_purge_nonblocking.sql');
$loginEndpoint = $read($root . '/public/api/login.php');
$adminLogin = $read($root . '/public/admin/index.php');
$helpers = $read($root . '/src/Helpers.php');
$bootstrap = $read($root . '/src/Bootstrap.php');
$financePage = $read($root . '/public/financeiro.php');
$financePay = $read($root . '/public/api/financeiro-pay.php');
$profile = $read($root . '/public/api/profile.php');
$adminReset = $read($root . '/public/api/admin-reset-password.php');
$mobile = $read($root . '/public/mobile/index.php');

$fake = new LoginThrottleFakeClient();
$throttle = new LoginThrottle($fake, 'segredo-de-teste-longo', '203.0.113.10');
$claim = $throttle->claim('guardian', '00624611175');
if (!($claim['ok'] ?? false) || !($claim['allowed'] ?? false)) {
    $failures[] = 'Throttle deveria permitir a tentativa autorizada pela RPC.';
}
$claimCall = $fake->calls[0] ?? [];
$keys = $claimCall['payload']['p_key_hashes'] ?? [];
$buckets = $claimCall['payload']['p_buckets'] ?? [];
if (($claimCall['function'] ?? '') !== 'claim_login_attempt' || count($keys) !== 3 || count($buckets) !== 3) {
    $failures[] = 'Throttle deveria adquirir três limites atômicos.';
}
foreach ($keys as $key) {
    if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/', $key) || str_contains($key, '00624611175')) {
        $failures[] = 'Chaves do throttle devem ser HMAC sem CPF ou IP em texto.';
    }
}
if (!$throttle->clearAfterSuccess()) {
    $failures[] = 'Login válido deveria limpar limites de conta e combinação.';
}
$clearKeys = $fake->calls[1]['payload']['p_key_hashes'] ?? [];
if (count($clearKeys) !== 3 || !in_array($keys[0] ?? '', $clearKeys, true)) {
    $failures[] = 'Sucesso deve liberar a reserva atual do IP sem apagar falhas concorrentes.';
}
$fake->allowed = false;
$blocked = (new LoginThrottle($fake, 'segredo-de-teste-longo', '203.0.113.10'))
    ->claim('admin', 'admin');
if (($blocked['allowed'] ?? true) !== false || (int) ($blocked['retry_after'] ?? 0) !== 300) {
    $failures[] = 'Throttle deveria propagar bloqueio e Retry-After.';
}

$contains('migration adiciona versão de sessão', $migration, 'account_session_version integer not null default 1');
$contains('migration cria throttle service-only', $migration, 'create table if not exists public.login_rate_limits');
$contains('migration usa locks atômicos', $migration, "pg_advisory_xact_lock(hashtextextended('login-rate:'");
$contains('migration habilita RLS', $migration, 'alter table public.login_rate_limits enable row level security');
$contains('migration rotaciona família em transação', $migration, 'create or replace function public.rotate_guardian_account_session');
$contains('migration restringe RPC de claim', $migration, 'grant execute on function public.claim_login_attempt(text[], text[]) to service_role');
$contains('migration corretiva decrementa reservas no sucesso', $hardeningMigration, 'v_row.attempt_count - 1');
$contains('limpeza saiu do claim e virou rotina dedicada', $hardeningMigration, 'create or replace function public.purge_stale_login_rate_limits');
$contains('purga usa o mesmo lock do claim', $purgeMigration, "pg_advisory_xact_lock(hashtextextended('login-rate:'");
$contains('purga final não pode aguardar lock', $nonBlockingPurgeMigration, 'pg_try_advisory_xact_lock');

$contains('login responsável usa throttle', $loginEndpoint, "\$throttle->claim('guardian'");
$contains('login responsável retorna 429', $loginEndpoint, '], 429)');
$order('throttle antecede autenticação', $loginEndpoint, "\$throttle->claim('guardian'", '$auth->login(');
$contains('login inicia sessão versionada', $loginEndpoint, 'Helpers::establishUserSession($user)');

$contains('login admin usa CSRF', $adminLogin, 'hash_equals($adminLoginCsrf, $submittedCsrf)');
$contains('login admin usa throttle', $adminLogin, "\$throttle->claim('admin'");
$contains('login admin retorna 429', $adminLogin, 'http_response_code(429)');

$contains('sessão possui expiração absoluta', $helpers, "['user_session_expires_at']");
$contains('sessão possui expiração por inatividade', $helpers, "['user_session_idle_expires_at']");
$contains('sessão reconsulta responsável', $helpers, "'guardians'");
$contains('sessão compara versão persistida', $helpers, '$storedVersion !== $freshVersion');
$contains('bootstrap remove sessão expirada', $bootstrap, '\\App\\Helpers::clearUserSession()');
$contains('mobile revalida sessão', $mobile, '\\App\\Helpers::currentUserSession()');

$contains('financeiro gera CSRF', $financePage, "['financeiro_csrf_token']");
$contains('financeiro usa formulário POST', $financePage, '<form method="post"');
$contains('pagamento exige POST', $financePay, "REQUEST_METHOD'] ?? 'GET') !== 'POST'");
$contains('pagamento valida CSRF', $financePay, 'hash_equals($expectedCsrfToken, $csrfToken)');
$notContains('pagamento não lê payment_id por GET', $financePay, "\$_GET['payment_id']");

$contains('perfil revoga outras sessões antes do Auth', $profile, '(new GuardianSession($client))->rotate(');
$order('perfil revoga antes de trocar senha Auth', $profile, '(new GuardianSession($client))->rotate(', '$auth->updateUser(');
$contains('reset administrativo revoga sessões antes do Auth', $adminReset, '(new GuardianSession($client))->rotate(');
$order('reset revoga antes de trocar senha Auth', $adminReset, '(new GuardianSession($client))->rotate(', '$auth->updateUser(');
$contains('reset administrativo exige CSRF', $adminReset, 'hash_equals($expectedCsrfToken, $csrfToken)');

$contains('financeiro limita responsável e aluno juntos', $financePage, '&guardian_id=eq.');
$notContains('financeiro não amplia escopo por CPF parcial', $financePage, 'parent_document=ilike.');
$contains('pagamento exige responsável e aluno', $financePay, '!hash_equals($sessionGuardianId, $paymentGuardianId)');
$order('pagamento valida cliente antes de promover pago', $financePay, '$remoteCustomerId ===', "['status' => 'paid'");
$contains('mutação remota impede liberar claim', $financePay, 'if ($remoteMutationOccurred)');

$contains('logout só encerra por POST', $read($root . '/public/logout.php'), "REQUEST_METHOD'] ?? 'GET') === 'POST'");
$contains('logout preserva contexto', $read($root . '/public/logout.php'), "if (\$context === 'admin')");

if ($failures !== []) {
    fwrite(STDERR, "Falhas na proteção de login e sessão:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "OK: throttle, CSRF financeiro e sessões versionadas validados.\n";
