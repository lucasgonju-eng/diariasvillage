<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Bootstrap.php';

use App\Services\OficinaModularGradeService;
use App\SupabaseClient;

$failures = [];

function check_workshop_auth(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

final class FakeWorkshopAuthorizationClient extends SupabaseClient
{
    public const OWNER_ID = '10000000-0000-4000-8000-000000000001';
    public const ATTACKER_ID = '20000000-0000-4000-8000-000000000002';
    public const DIARIA_ID = '30000000-0000-4000-8000-000000000003';
    public const WORKSHOP_ID = '40000000-0000-4000-8000-000000000004';

    /** @var array<int, array{table:string,query:string}> */
    public array $selectCalls = [];
    /** @var array<int, array{function:string,payload:array<string,mixed>}> */
    public array $rpcCalls = [];

    public function __construct()
    {
    }

    public function select(string $table, string $query = ''): array
    {
        $this->selectCalls[] = ['table' => $table, 'query' => $query];

        if ($table === 'diaria') {
            if (!str_contains($query, 'guardian_id=eq.' . self::OWNER_ID)) {
                return ['ok' => true, 'data' => []];
            }
            return ['ok' => true, 'data' => [[
                'id' => self::DIARIA_ID,
                'guardian_id' => self::OWNER_ID,
                'data_diaria' => '2026-09-03',
                'status_pagamento' => 'PENDENTE',
                'grade_travada' => false,
            ]]];
        }

        if ($table === 'oficina_modular') {
            return ['ok' => true, 'data' => [[
                'id' => self::WORKSHOP_ID,
                'ativa' => true,
                'status_quorum' => 'ATIVA',
                'tipo' => 'RECORRENTE',
            ]]];
        }

        if ($table === 'oficina_modular_horarios') {
            return ['ok' => true, 'data' => [[
                'id' => '50000000-0000-4000-8000-000000000005',
                'oficina_modular_id' => self::WORKSHOP_ID,
                'dia_semana' => 4,
                'hora_inicio' => '14:00:00',
                'hora_fim' => '15:00:00',
            ]]];
        }

        if ($table === 'diaria_slots_travados') {
            return ['ok' => true, 'data' => []];
        }

        if ($table === 'diaria_oficina_modular_reserva') {
            return ['ok' => true, 'data' => [[
                'id' => '60000000-0000-4000-8000-000000000006',
                'diaria_id' => self::DIARIA_ID,
                'oficina_modular_id' => self::WORKSHOP_ID,
                'status' => 'RASCUNHO',
            ]]];
        }

        return ['ok' => true, 'data' => []];
    }

    public function rpc(string $functionName, array $payload = []): array
    {
        $this->rpcCalls[] = ['function' => $functionName, 'payload' => $payload];
        return ['ok' => true, 'data' => ['ok' => true]];
    }
}

$client = new FakeWorkshopAuthorizationClient();
$service = new OficinaModularGradeService($client);

$deniedSelection = $service->selecionarOficinaModular(
    FakeWorkshopAuthorizationClient::DIARIA_ID,
    FakeWorkshopAuthorizationClient::ATTACKER_ID,
    FakeWorkshopAuthorizationClient::WORKSHOP_ID
);
check_workshop_auth(
    ($deniedSelection['ok'] ?? true) === false
        && ($deniedSelection['error'] ?? '') === 'Diária não encontrada.',
    'seleção de diária alheia deve ser negada sem revelar sua existência'
);
check_workshop_auth($client->rpcCalls === [], 'seleção negada não pode alcançar a RPC mutável');

$allowedSelection = $service->selecionarOficinaModular(
    FakeWorkshopAuthorizationClient::DIARIA_ID,
    FakeWorkshopAuthorizationClient::OWNER_ID,
    FakeWorkshopAuthorizationClient::WORKSHOP_ID
);
check_workshop_auth(($allowedSelection['ok'] ?? false) === true, 'dono deve conseguir selecionar oficina');
check_workshop_auth(
    ($client->rpcCalls[0]['payload']['p_guardian_id'] ?? '') === FakeWorkshopAuthorizationClient::OWNER_ID,
    'RPC de seleção deve receber o responsável autenticado'
);

$allowedRemoval = $service->removerOficinaModular(
    FakeWorkshopAuthorizationClient::DIARIA_ID,
    FakeWorkshopAuthorizationClient::OWNER_ID,
    FakeWorkshopAuthorizationClient::WORKSHOP_ID
);
check_workshop_auth(($allowedRemoval['ok'] ?? false) === true, 'dono deve conseguir remover oficina');
check_workshop_auth(
    ($client->rpcCalls[1]['payload']['p_guardian_id'] ?? '') === FakeWorkshopAuthorizationClient::OWNER_ID,
    'RPC de remoção deve receber o responsável autenticado'
);

$diariaQueries = array_column(
    array_values(array_filter(
        $client->selectCalls,
        static fn (array $call): bool => $call['table'] === 'diaria'
    )),
    'query'
);
check_workshop_auth(
    $diariaQueries !== []
        && !array_filter($diariaQueries, static fn (string $query): bool =>
            !str_contains($query, 'guardian_id=eq.')
            || !str_contains($query, 'status_pagamento=eq.PENDENTE')
            || !str_contains($query, 'grade_travada=eq.false')
        ),
    'consulta da diária deve sempre filtrar dono e estado mutável'
);

$migration = file_get_contents(
    dirname(__DIR__) . '/supabase/migrations/20260903015356_lock_workshop_grade_to_guardian.sql'
);
check_workshop_auth(
    is_string($migration)
        && substr_count($migration, 'guardian_id = p_guardian_id') === 2
        && substr_count($migration, 'for update;') === 2,
    'as duas RPCs devem revalidar e bloquear a diária do responsável'
);
check_workshop_auth(
    is_string($migration)
        && str_contains($migration, 'drop function if exists public.oficina_modular_grade_travar_e_reservar')
        && str_contains($migration, 'drop function if exists public.oficina_modular_grade_liberar_e_cancelar'),
    'assinaturas antigas sem guardian_id devem ser removidas'
);
check_workshop_auth(
    is_string($migration)
        && substr_count($migration, 'from public, anon, authenticated;') === 2
        && substr_count($migration, 'to service_role;') === 2,
    'RPCs mutáveis devem permanecer exclusivas do service_role'
);

if ($failures !== []) {
    fwrite(STDERR, "Falhas na autorização de oficinas:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK: seleção e remoção de oficinas estão vinculadas ao responsável da sessão.\n";
