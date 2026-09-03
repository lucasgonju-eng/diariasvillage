<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Bootstrap.php';

use App\AsaasClient;
use App\AsaasCustomerIdentity;
use App\SupabaseClient;

$failures = [];

function check_identity_behavior(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

final class FakeIdentityAsaasClient extends AsaasClient
{
    /** @var array<string, mixed> */
    public array $remoteCustomer = [];
    /** @var array<int, array<string, mixed>> */
    public array $calls = [];

    public function __construct()
    {
    }

    public function getCustomer(string $customerId): array
    {
        $this->calls[] = ['method' => 'getCustomer', 'id' => $customerId];
        return ['ok' => true, 'status' => 200, 'data' => $this->remoteCustomer];
    }

    public function updateCustomer(string $customerId, array $payload): array
    {
        $this->calls[] = ['method' => 'updateCustomer', 'id' => $customerId, 'payload' => $payload];
        return ['ok' => true, 'status' => 200, 'data' => ['id' => $customerId]];
    }
}

final class FakeIdentitySupabaseClient extends SupabaseClient
{
    /** @var array<int, array<string, mixed>> */
    public array $updates = [];
    /** @var array<int, array<string, mixed>> */
    public array $guardianRows = [];

    public function __construct()
    {
    }

    public function select(string $table, string $query = ''): array
    {
        return ['ok' => true, 'status' => 200, 'data' => $this->guardianRows];
    }

    public function update(string $table, string $query, array $payload): array
    {
        $this->updates[] = ['table' => $table, 'query' => $query, 'payload' => $payload];
        return ['ok' => true, 'status' => 200, 'data' => [['id' => $payload['asaas_customer_id'] ?? 'ok']]];
    }
}

function guardian_fixture(): array
{
    return [
        'id' => '10000000-0000-4000-8000-000000000001',
        'parent_name' => 'Maria da Silva',
        'email' => 'maria@example.com',
        'parent_phone' => '61999999999',
        'parent_document' => '529.982.247-25',
        'asaas_customer_id' => 'cus_family',
    ];
}

function resolve_with_remote(array $remote, ?array $guardian = null, ?array $guardianRows = null): array
{
    $guardian ??= guardian_fixture();
    $asaas = new FakeIdentityAsaasClient();
    $asaas->remoteCustomer = $remote;
    $database = new FakeIdentitySupabaseClient();
    $database->guardianRows = $guardianRows ?? [$guardian];
    $result = (new AsaasCustomerIdentity($asaas, $database))->resolve($guardian);

    return [$result, $asaas, $database];
}

[$exactResult, $exactAsaas, $exactDatabase] = resolve_with_remote([
    'id' => 'cus_family',
    'name' => 'Maria da Silva',
    'email' => 'maria@example.com',
    'cpfCnpj' => '52998224725',
    'deleted' => false,
]);
check_identity_behavior(($exactResult['ok'] ?? false) === true, 'identidade composta exata deve ser reutilizada');
check_identity_behavior(
    array_column($exactAsaas->calls, 'method') === ['getCustomer', 'updateCustomer'],
    'cliente exato pode ser sincronizado somente após validação'
);
check_identity_behavior(count($exactDatabase->updates) === 1, 'vínculo exato deve ser persistido localmente');
$exactPayload = $exactAsaas->calls[1]['payload'] ?? [];
check_identity_behavior(
    ($exactPayload['name'] ?? '') === 'Maria da Silva'
        && ($exactPayload['email'] ?? '') === 'maria@example.com'
        && ($exactPayload['cpfCnpj'] ?? '') === '52998224725',
    'sincronização deve transportar a identidade completa'
);

[$localConflict, $localConflictAsaas] = resolve_with_remote(
    [
        'id' => 'cus_family',
        'name' => 'Maria da Silva',
        'email' => 'maria@example.com',
        'cpfCnpj' => '52998224725',
        'deleted' => false,
    ],
    guardian_fixture(),
    [
        guardian_fixture(),
        [
            'id' => '10000000-0000-4000-8000-000000000002',
            'parent_name' => 'Maria da Silva',
            'email' => 'email-conflitante@example.com',
            'parent_phone' => '61999999999',
            'parent_document' => '52998224725',
            'asaas_customer_id' => 'cus_family',
        ],
    ]
);
check_identity_behavior(
    ($localConflict['code'] ?? '') === 'GUARDIAN_DOCUMENT_CONFLICT',
    'mesmo documento e nome com outro e-mail local deve bloquear'
);
check_identity_behavior($localConflictAsaas->calls === [], 'conflito local deve bloquear antes de consultar o Asaas');

[$incompleteDuplicate, $incompleteDuplicateAsaas] = resolve_with_remote(
    [
        'id' => 'cus_family',
        'name' => 'Maria da Silva',
        'email' => 'maria@example.com',
        'cpfCnpj' => '52998224725',
        'deleted' => false,
    ],
    guardian_fixture(),
    [
        guardian_fixture(),
        [
            'id' => '10000000-0000-4000-8000-000000000003',
            'parent_name' => 'Maria da Silva',
            'email' => '',
            'parent_document' => '52998224725',
            'asaas_customer_id' => '',
        ],
    ]
);
check_identity_behavior(
    ($incompleteDuplicate['code'] ?? '') === 'GUARDIAN_DOCUMENT_CONFLICT',
    'CPF repetido com e-mail vazio deve bloquear para revisão'
);
check_identity_behavior(
    $incompleteDuplicateAsaas->calls === [],
    'duplicidade local incompleta deve bloquear antes de consultar o Asaas'
);

[$emailConflict, $emailAsaas, $emailDatabase] = resolve_with_remote([
    'id' => 'cus_family',
    'name' => 'Maria da Silva',
    'email' => 'outra@example.com',
    'cpfCnpj' => '52998224725',
    'deleted' => false,
]);
check_identity_behavior(
    ($emailConflict['code'] ?? '') === 'ASAAS_CUSTOMER_IDENTITY_CONFLICT',
    'mesmo documento com e-mail divergente deve bloquear'
);
check_identity_behavior(count($emailAsaas->calls) === 1, 'conflito de e-mail não pode mutar cliente Asaas');
check_identity_behavior($emailDatabase->updates === [], 'conflito de e-mail não pode alterar vínculo local');

[$nameConflict, $nameAsaas, $nameDatabase] = resolve_with_remote([
    'id' => 'cus_family',
    'name' => 'Outra Pessoa',
    'email' => 'maria@example.com',
    'cpfCnpj' => '52998224725',
    'deleted' => false,
]);
check_identity_behavior(
    ($nameConflict['code'] ?? '') === 'ASAAS_CUSTOMER_IDENTITY_CONFLICT',
    'mesmo documento com nome divergente deve bloquear'
);
check_identity_behavior(count($nameAsaas->calls) === 1, 'conflito de nome não pode mutar cliente Asaas');
check_identity_behavior($nameDatabase->updates === [], 'conflito de nome não pode alterar vínculo local');

[$missingDocument, $missingDocumentAsaas] = resolve_with_remote([
    'id' => 'cus_family',
    'name' => 'Maria da Silva',
    'email' => 'maria@example.com',
    'cpfCnpj' => '',
    'deleted' => false,
]);
check_identity_behavior(
    ($missingDocument['code'] ?? '') === 'ASAAS_CUSTOMER_DOCUMENT_CONFLICT',
    'cliente remoto sem documento deve bloquear enriquecimento automático'
);
check_identity_behavior(count($missingDocumentAsaas->calls) === 1, 'documento ausente não pode ser preenchido automaticamente');

[$documentConflict, $documentAsaas] = resolve_with_remote([
    'id' => 'cus_family',
    'name' => 'Maria da Silva',
    'email' => 'maria@example.com',
    'cpfCnpj' => '11144477735',
    'deleted' => false,
]);
check_identity_behavior(
    ($documentConflict['code'] ?? '') === 'ASAAS_CUSTOMER_DOCUMENT_CONFLICT',
    'documento remoto divergente deve bloquear'
);
check_identity_behavior(count($documentAsaas->calls) === 1, 'conflito documental não pode mutar cliente Asaas');

if ($failures !== []) {
    fwrite(STDERR, "Falhas no resolvedor de identidade:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK: resolvedor Asaas exige nome, e-mail e documento exatos antes de sincronizar.\n";
