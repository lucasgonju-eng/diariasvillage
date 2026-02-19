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
use App\Services\OficinaModularGradeService;
use App\SupabaseClient;

Helpers::requirePost();
$user = Helpers::requireAuth();

$diariaId = isset($_GET['diariaId']) ? trim((string) $_GET['diariaId']) : '';
$oficinaId = isset($_GET['oficinaId']) ? trim((string) $_GET['oficinaId']) : '';
if ($diariaId === '' || $oficinaId === '') {
    Helpers::json(['ok' => false, 'error' => 'Parâmetros inválidos para upsell.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$guardian = $client->select('guardians', 'select=id&id=eq.' . rawurlencode((string) $user['id']) . '&limit=1');
if (!$guardian['ok'] || empty($guardian['data'][0]['id'])) {
    Helpers::json(['ok' => false, 'error' => 'Responsável não encontrado.'], 404);
}
$guardianId = (string) $guardian['data'][0]['id'];

$service = new OficinaModularGradeService($client);
$result = $service->criarUpsellSegundoEncontro($diariaId, $oficinaId, $guardianId);
if (($result['ok'] ?? false) !== true) {
    Helpers::json($result, 422);
}

Helpers::json($result);
