<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Bootstrap.php';

use App\Auth;
use App\GuardianAccountIdentity;
use App\SupabaseAuth;
use App\SupabaseClient;

$failures = [];

function account_check(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

function account_guardian(
    string $id,
    string $email,
    ?string $authUserId = null,
    string $name = 'Maria da Silva',
    string $document = '52998224725'
): array {
    return [
        'id' => $id,
        'student_id' => '30000000-0000-4000-8000-000000000001',
        'parent_name' => $name,
        'parent_document' => $document,
        'email' => $email,
        'auth_user_id' => $authUserId,
        'password_hash' => password_hash('senha-antiga', PASSWORD_DEFAULT),
        'verified_at' => '2026-09-01T12:00:00Z',
    ];
}

final class AccountIdentityFakeDatabase extends SupabaseClient
{
    /** @var array<int, array<string, mixed>> */
    public array $cpfRows = [];
    /** @var array<int, array<string, mixed>> */
    public array $accountRows = [];

    public function __construct()
    {
    }

    public function select(string $table, string $query = ''): array
    {
        if ($table === 'guardians' && str_contains($query, 'auth_user_id=eq.')) {
            return ['ok' => true, 'data' => $this->accountRows];
        }
        if ($table === 'guardians') {
            return ['ok' => true, 'data' => $this->cpfRows];
        }
        return ['ok' => true, 'data' => []];
    }

    public function selectAll(
        string $table,
        string $query = '',
        int $pageSize = 500,
        int $maxPages = 100
    ): array {
        return $this->select($table, $query);
    }
}

final class AccountIdentityFakeAuth extends SupabaseAuth
{
    /** @var array<string, array<string, mixed>> */
    public array $responses = [];
    /** @var array<int, string> */
    public array $calls = [];

    public function __construct()
    {
    }

    public function signIn(string $email, string $password): array
    {
        $this->calls[] = strtolower($email);
        return $this->responses[strtolower($email)]
            ?? ['ok' => false, 'data' => ['error_description' => 'Invalid login credentials']];
    }
}

final class PaginatedAccountFakeDatabase extends SupabaseClient
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];
    public int $calls = 0;

    public function __construct()
    {
    }

    public function select(string $table, string $query = ''): array
    {
        $this->calls++;
        preg_match('/(?:^|&)limit=(\d+)/', $query, $limitMatch);
        preg_match('/(?:^|&)offset=(\d+)/', $query, $offsetMatch);
        $limit = (int) ($limitMatch[1] ?? 500);
        $offset = (int) ($offsetMatch[1] ?? 0);
        return ['ok' => true, 'data' => array_slice($this->rows, $offset, $limit)];
    }
}

$paginationDatabase = new PaginatedAccountFakeDatabase();
$paginationDatabase->rows = array_fill(0, 1200, ['id' => 'row']);
$paginated = $paginationDatabase->selectAll('guardians', 'select=id&order=id.asc', 500, 10);
account_check(($paginated['ok'] ?? false) === true, 'paginação deve retornar todas as linhas');
account_check(count($paginated['data'] ?? []) === 1200, 'paginação não pode truncar conflitos');
account_check($paginationDatabase->calls === 3, 'paginação deve continuar até uma página incompleta');

$paginationLimitDatabase = new PaginatedAccountFakeDatabase();
$paginationLimitDatabase->rows = array_fill(0, 1000, ['id' => 'row']);
$limited = $paginationLimitDatabase->selectAll('guardians', 'select=id&order=id.asc', 500, 2);
account_check(($limited['ok'] ?? true) === false, 'limite de paginação deve falhar fechado');

$legacy = account_guardian('10000000-0000-4000-8000-000000000001', 'maria@example.com');
$legacyAnalysis = GuardianAccountIdentity::analyze([$legacy], $legacy['id']);
account_check(($legacyAnalysis['ok'] ?? false) === true, 'um vínculo legado único deve ser determinístico');
account_check(($legacyAnalysis['mode'] ?? '') === 'legacy_local', 'vínculo sem auth_user_id deve permanecer legado');

$brokenActivated = $legacy;
$brokenActivated['first_access_completed_at'] = '2026-09-01T12:00:00Z';
$brokenActivatedAnalysis = GuardianAccountIdentity::analyze([$brokenActivated]);
account_check(
    ($brokenActivatedAnalysis['code'] ?? '') === 'GUARDIAN_AUTH_LINK_MISSING_AFTER_ACTIVATION',
    'primeiro acesso concluído sem auth_user_id nunca pode voltar ao hash legado'
);

$duplicateLegacy = GuardianAccountIdentity::analyze([
    $legacy,
    account_guardian('10000000-0000-4000-8000-000000000002', 'outro@example.com'),
]);
account_check(
    ($duplicateLegacy['code'] ?? '') === 'GUARDIAN_LEGACY_IDENTITY_AMBIGUOUS',
    'CPF legado em mais de uma linha deve bloquear'
);

$sharedAuthId = '20000000-0000-4000-8000-000000000001';
$familyRows = [
    account_guardian('10000000-0000-4000-8000-000000000001', 'maria@example.com', $sharedAuthId),
    account_guardian('10000000-0000-4000-8000-000000000002', 'antigo@example.com', $sharedAuthId),
];
$familyAnalysis = GuardianAccountIdentity::analyze($familyRows, $familyRows[1]['id']);
account_check(($familyAnalysis['ok'] ?? false) === true, 'múltiplos filhos podem compartilhar uma única conta Auth');
account_check(($familyAnalysis['mode'] ?? '') === 'supabase_auth', 'família vinculada deve usar modo Auth');
account_check(($familyAnalysis['auth_user_id'] ?? '') === $sharedAuthId, 'conta Auth deve ser identificada por UUID');

$mixedLinks = $familyRows;
$mixedLinks[1]['auth_user_id'] = null;
$mixedAnalysis = GuardianAccountIdentity::analyze($mixedLinks);
account_check(
    ($mixedAnalysis['code'] ?? '') === 'GUARDIAN_AUTH_LINK_INCOMPLETE',
    'vínculo Auth parcial deve bloquear'
);

$multipleAccounts = $familyRows;
$multipleAccounts[1]['auth_user_id'] = '20000000-0000-4000-8000-000000000002';
$multipleAccountAnalysis = GuardianAccountIdentity::analyze($multipleAccounts);
account_check(
    ($multipleAccountAnalysis['code'] ?? '') === 'GUARDIAN_AUTH_ACCOUNT_CONFLICT',
    'mesmo CPF com duas contas Auth deve bloquear'
);

$differentNames = $familyRows;
$differentNames[1]['parent_name'] = 'Outra Pessoa';
$differentNameAnalysis = GuardianAccountIdentity::analyze($differentNames);
account_check(
    ($differentNameAnalysis['code'] ?? '') === 'GUARDIAN_NAME_CONFLICT',
    'mesmo CPF com nomes distintos deve bloquear'
);

$selectionMismatch = GuardianAccountIdentity::analyze(
    [$legacy],
    '10000000-0000-4000-8000-000000000099'
);
account_check(
    ($selectionMismatch['code'] ?? '') === 'GUARDIAN_SELECTION_MISMATCH',
    'guardian_id fora do grupo deve bloquear'
);

$authDatabase = new AccountIdentityFakeDatabase();
$authDatabase->cpfRows = $familyRows;
$authDatabase->accountRows = $familyRows;
$authClient = new AccountIdentityFakeAuth();
$authClient->responses['maria@example.com'] = [
    'ok' => true,
    'data' => ['user' => ['id' => $sharedAuthId]],
];
$authLogin = (new Auth($authDatabase, $authClient))->login('52998224725', 'senha-nova');
account_check(($authLogin['ok'] ?? false) === true, 'login Auth deve aceitar somente o UUID vinculado');

$wrongAuthDatabase = new AccountIdentityFakeDatabase();
$wrongAuthDatabase->cpfRows = $familyRows;
$wrongAuthDatabase->accountRows = $familyRows;
$wrongAuthClient = new AccountIdentityFakeAuth();
$wrongAuthClient->responses['maria@example.com'] = [
    'ok' => true,
    'data' => ['user' => ['id' => '20000000-0000-4000-8000-000000000099']],
];
$wrongAuthLogin = (new Auth($wrongAuthDatabase, $wrongAuthClient))->login('52998224725', 'senha-antiga');
account_check(($wrongAuthLogin['ok'] ?? true) === false, 'resposta Auth de outro UUID deve bloquear');
account_check(
    count($wrongAuthClient->calls) === 2,
    'conta vinculada pode testar seus e-mails, mas nunca usar fallback de hash local'
);

$conflictDatabase = new AccountIdentityFakeDatabase();
$conflictDatabase->cpfRows = $multipleAccounts;
$conflictDatabase->accountRows = $multipleAccounts;
$conflictAuth = new AccountIdentityFakeAuth();
$conflictLogin = (new Auth($conflictDatabase, $conflictAuth))->login('52998224725', 'senha-antiga');
account_check(($conflictLogin['ok'] ?? true) === false, 'CPF com duas contas Auth não pode autenticar');
account_check($conflictAuth->calls === [], 'conflito local deve bloquear antes do Supabase Auth');

$legacyDatabase = new AccountIdentityFakeDatabase();
$legacyDatabase->cpfRows = [$legacy];
$legacyAuth = new AccountIdentityFakeAuth();
$legacyLogin = (new Auth($legacyDatabase, $legacyAuth))->login('52998224725', 'senha-antiga');
account_check(($legacyLogin['ok'] ?? false) === true, 'vínculo legado único ainda pode usar hash local');

$invalidCpfDatabase = new AccountIdentityFakeDatabase();
$invalidCpfDatabase->cpfRows = [$legacy];
$invalidCpfAuth = new AccountIdentityFakeAuth();
$invalidCpfLogin = (new Auth($invalidCpfDatabase, $invalidCpfAuth))->login('11111111111', 'senha-antiga');
account_check(($invalidCpfLogin['ok'] ?? true) === false, 'CPF sem dígitos verificadores válidos deve bloquear');
account_check($invalidCpfAuth->calls === [], 'CPF inválido deve bloquear antes do Auth');

$reset = file_get_contents(dirname(__DIR__) . '/public/api/admin-reset-password.php') ?: '';
$dashboard = file_get_contents(dirname(__DIR__) . '/public/admin/dashboard.php') ?: '';
$javascript = file_get_contents(dirname(__DIR__) . '/public/assets/js/admin-dashboard.js') ?: '';
$loginEndpoint = file_get_contents(dirname(__DIR__) . '/public/api/login.php') ?: '';
$supabaseClient = file_get_contents(dirname(__DIR__) . '/src/SupabaseClient.php') ?: '';

foreach ([
    "__DIR__ . '/../src/Bootstrap.php'" => 'reset deve carregar bootstrap na estrutura pública',
    'GuardianAccountIdentity::analyze(' => 'reset deve analisar a conta composta',
    "if (\$action === 'lookup')" => 'reset deve possuir etapa de consulta',
    "'guardian_id'" => 'reset deve exigir guardian_id explícito',
    '$auth->updateUser($authUserId' => 'reset deve atualizar Auth por UUID',
    "'auth_user_id=eq.'" => 'reset deve atualizar somente os vínculos da conta',
    "'GUARDIAN_PASSWORD_RESET'" => 'reset deve gerar auditoria administrativa',
    "'state' => 'STARTED'" => 'auditoria deve existir antes da mutação',
    'LOCAL_FALLBACK_DISABLE_FAILED' => 'fallback local deve ser invalidado antes do Auth',
] as $needle => $message) {
    account_check(str_contains($reset, $needle), $message);
}
account_check(!str_contains($reset, 'listUsers('), 'reset não pode procurar usuário Auth por e-mail');
account_check(!str_contains($reset, 'admin_username'), 'auditoria deve usar a chave real da sessão');
account_check(
    strpos($reset, '$auditStart =') < strpos($reset, '$auth->updateUser('),
    'auditoria STARTED deve anteceder a alteração remota'
);
account_check(str_contains($dashboard, 'id="reset-guardian"'), 'dashboard deve mostrar seleção explícita');
account_check(str_contains($dashboard, 'admin-dashboard.js?v=80'), 'dashboard deve invalidar cache do JavaScript');
account_check(
    str_contains($javascript, "action: 'lookup'") && str_contains($javascript, 'guardian_id: guardianId'),
    'frontend deve consultar identidade e enviar o guardian_id escolhido'
);
account_check(
    str_contains($loginEndpoint, 'AsaasCustomerIdentity::isValidCpfOrCnpj($cpfDigits)'),
    'endpoint de login deve validar os dígitos verificadores'
);
account_check(
    str_contains($supabaseClient, 'function selectAll(') && str_contains($supabaseClient, "'&offset='"),
    'consultas de identidade devem possuir paginação reutilizável'
);

if ($failures !== []) {
    fwrite(STDERR, "Falhas na identidade de conta familiar:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK: reset e login bloqueiam CPFs ambíguos e usam auth_user_id explícito.\n";
