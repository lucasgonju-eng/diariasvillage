<?php
$bootstrapCandidates = [
    __DIR__ . '/../src/Bootstrap.php',
    dirname(__DIR__, 2) . '/src/Bootstrap.php',
];
foreach ($bootstrapCandidates as $bootstrapFile) {
    if (is_file($bootstrapFile)) {
        require_once $bootstrapFile;
        break;
    }
}

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

$client = new SupabaseClient(new HttpClient());
$result = $client->select(
    'oficina_modular',
    'select=id,nome,codigo,descricao,tipo,data_inicio_validade,data_fim_validade,ativa&ativa=eq.true&order=nome.asc&limit=500'
);

if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
    Helpers::json(['ok' => true, 'offices' => []]);
}

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$offices = [];

foreach ($result['data'] as $row) {
    if (!is_array($row)) {
        continue;
    }
    $id = trim((string) ($row['id'] ?? ''));
    $name = trim((string) ($row['nome'] ?? ''));
    $description = (string) ($row['descricao'] ?? '');
    if ($id === '' || $name === '') {
        continue;
    }
    $descriptionCheck = function_exists('mb_strtoupper')
        ? mb_strtoupper($description, 'UTF-8')
        : strtoupper($description);
    if (!str_contains($descriptionCheck, '[CATALOGO_OM_MENSAL]')) {
        continue;
    }
    $tipo = strtoupper(trim((string) ($row['tipo'] ?? '')));
    if ($tipo !== 'OCASIONAL_30D') {
        continue;
    }
    $start = trim((string) ($row['data_inicio_validade'] ?? ''));
    $end = trim((string) ($row['data_fim_validade'] ?? ''));
    if ($start === '' || $end === '') {
        continue;
    }
    if (!($start <= $monthEnd && $end >= $monthStart)) {
        continue;
    }

    $code = trim((string) ($row['codigo'] ?? ''));
    $label = $name . ($code !== '' ? ' (' . $code . ')' : '');
    $offices[] = [
        'id' => $id,
        'name' => $name,
        'code' => $code,
        'label' => $label,
    ];
}

Helpers::json([
    'ok' => true,
    'offices' => $offices,
    'month_start' => $monthStart,
    'month_end' => $monthEnd,
]);

