<?php

require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

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
$result = $service->removerOficinaModular($diariaId, $oficinaId, $slotId !== '' ? $slotId : null);

if (($result['ok'] ?? false) === true) {
    Helpers::json($result);
}

if (($result['error'] ?? '') === 'Diária não encontrada.') {
    Helpers::json($result, 404);
}

Helpers::json($result, 422);
