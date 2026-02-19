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
use App\Services\OficinaModularGradeService;

Helpers::requirePost();
Helpers::requireAuth();

$diariaId = isset($_GET['diariaId']) ? trim((string) $_GET['diariaId']) : '';
$oficinaId = isset($_GET['oficinaId']) ? trim((string) $_GET['oficinaId']) : '';
$slotId = isset($_GET['slotId']) ? trim((string) $_GET['slotId']) : '';

if ($diariaId === '' || $oficinaId === '') {
    Helpers::json([
        'ok' => false,
        'error' => 'Parâmetros de rota inválidos.',
    ], 422);
}

$service = new OficinaModularGradeService();
$result = $service->selecionarOficinaModular($diariaId, $oficinaId, $slotId !== '' ? $slotId : null);

if (($result['ok'] ?? false) === true) {
    Helpers::json($result);
}

$reason = (string) ($result['reason'] ?? '');
if ($reason === 'CONFLITO_SLOT') {
    Helpers::json($result, 409);
}

if ($reason === 'OFICINA_FORA_DO_DIA') {
    Helpers::json($result, 422);
}

if (($result['error'] ?? '') === 'Diária não encontrada.' || ($result['error'] ?? '') === 'Oficina Modular não encontrada.') {
    Helpers::json($result, 404);
}

Helpers::json($result, 422);
