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

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    Helpers::json(['ok' => false, 'error' => 'Método inválido.'], 405);
}
$user = Helpers::requireAuth();

$payload = [];
$contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
if ($method === 'POST' && stripos($contentType, 'application/json') !== false) {
    $jsonPayload = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($jsonPayload)) {
        $payload = $jsonPayload;
    }
}
if (empty($payload) && $method === 'POST' && !empty($_POST)) {
    $payload = $_POST;
}
if (empty($payload) && $method === 'GET' && !empty($_GET)) {
    $payload = $_GET;
}

$diariaId = isset($payload['diaria_id']) ? trim((string) $payload['diaria_id']) : '';
$orientadoraSlotsPayload = $payload['orientadora_slots'] ?? [];
if (!is_array($orientadoraSlotsPayload)) {
    // Aceita fallback via querystring (JSON ou CSV) para contornar bloqueio de POST por WAF.
    $rawSlots = trim((string) $orientadoraSlotsPayload);
    $decodedSlots = json_decode($rawSlots, true);
    if (is_array($decodedSlots)) {
        $orientadoraSlotsPayload = $decodedSlots;
    } elseif ($rawSlots !== '') {
        $orientadoraSlotsPayload = array_values(array_filter(array_map('trim', explode(',', $rawSlots))));
    } else {
        $orientadoraSlotsPayload = [];
    }
}

if ($diariaId === '') {
    Helpers::json(['ok' => false, 'error' => 'Diária não informada.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$guardian = $client->select('guardians', 'select=id&id=eq.' . rawurlencode((string) $user['id']) . '&limit=1');
if (!$guardian['ok'] || empty($guardian['data'][0]['id'])) {
    Helpers::json(['ok' => false, 'error' => 'Responsável não encontrado.'], 404);
}
$guardianId = (string) $guardian['data'][0]['id'];

$diaria = $client->select(
    'diaria',
    'select=id'
    . '&id=eq.' . rawurlencode($diariaId)
    . '&guardian_id=eq.' . rawurlencode($guardianId)
    . '&limit=1'
);
if (!$diaria['ok'] || empty($diaria['data'])) {
    Helpers::json(['ok' => false, 'error' => 'Diária não encontrada.'], 404);
}

$service = new OficinaModularGradeService($client);
$revalidacao = $service->revalidarGradeAntesDoCheckout($diariaId);
if (($revalidacao['ok'] ?? false) !== true) {
    Helpers::json([
        'ok' => false,
        'error' => (string) ($revalidacao['message'] ?? $revalidacao['error'] ?? 'Não foi possível concluir a etapa da grade.'),
        'changed' => (bool) ($revalidacao['changed'] ?? false),
        'canceladas' => (int) ($revalidacao['canceladas'] ?? 0),
    ], 422);
}

// Regra da diária: o responsável deve fechar os 2 horários úteis.
$diariaDetalhe = $client->select(
    'diaria',
    'select=id,data_diaria'
    . '&id=eq.' . rawurlencode($diariaId)
    . '&guardian_id=eq.' . rawurlencode($guardianId)
    . '&limit=1'
);
if (!$diariaDetalhe['ok'] || empty($diariaDetalhe['data'][0])) {
    Helpers::json(['ok' => false, 'error' => 'Diária não encontrada.'], 404);
}

$dataDiaria = (string) ($diariaDetalhe['data'][0]['data_diaria'] ?? '');
$dt = \DateTimeImmutable::createFromFormat('Y-m-d', $dataDiaria);
if (!$dt instanceof \DateTimeImmutable) {
    Helpers::json(['ok' => false, 'error' => 'Data da diária inválida.'], 422);
}
$diaSemana = (int) $dt->format('N');
if ($diaSemana >= 1 && $diaSemana <= 5) {
    $slot1400 = $service->buildSlotIdFromDayAndTime($diaSemana, '14:00');
    $slot1540 = $service->buildSlotIdFromDayAndTime($diaSemana, '15:40');
    $expectedSlots = array_values(array_filter([$slot1400, $slot1540], static fn($v) => is_string($v) && $v !== ''));

    $travadosResult = $client->select(
        'diaria_slots_travados',
        'select=slot_id'
        . '&diaria_id=eq.' . rawurlencode($diariaId)
    );
    $travadosSlots = [];
    if ($travadosResult['ok'] && is_array($travadosResult['data'])) {
        foreach ($travadosResult['data'] as $row) {
            $slotId = trim((string) ($row['slot_id'] ?? ''));
            if ($slotId !== '') {
                $travadosSlots[$slotId] = true;
            }
        }
    }

    $orientadoraSlots = [];
    if (is_array($orientadoraSlotsPayload)) {
        foreach ($orientadoraSlotsPayload as $slot) {
            $slotId = trim((string) $slot);
            if ($slotId !== '') {
                $orientadoraSlots[$slotId] = true;
            }
        }
    }

    foreach ($expectedSlots as $slotIdEsperado) {
        if (!isset($travadosSlots[$slotIdEsperado]) && !isset($orientadoraSlots[$slotIdEsperado])) {
            Helpers::json([
                'ok' => false,
                'error' => 'Para diária, é obrigatório escolher os 2 horários (14:00 e 15:40) ou marcar a escolha pela Orientadora.',
            ], 422);
        }
    }
}

// Marca a diária como concluída na etapa da grade para liberar o create-payment.
$updateDiaria = $client->update('diaria', 'id=eq.' . rawurlencode($diariaId), [
    'grade_oficina_modular_ok' => true,
    'updated_at' => date('c'),
]);
if (!($updateDiaria['ok'] ?? false)) {
    Helpers::json([
        'ok' => false,
        'error' => 'Não foi possível concluir a etapa da grade. Tente novamente.',
    ], 500);
}

if (!isset($_SESSION['grade_checkout_ready']) || !is_array($_SESSION['grade_checkout_ready'])) {
    $_SESSION['grade_checkout_ready'] = [];
}
if (!isset($_SESSION['grade_checkout_tokens']) || !is_array($_SESSION['grade_checkout_tokens'])) {
    $_SESSION['grade_checkout_tokens'] = [];
}
// Limpa flags antigas para não manter sessão crescendo.
foreach ($_SESSION['grade_checkout_ready'] as $id => $ts) {
    $tsInt = is_int($ts) ? $ts : (int) $ts;
    if ($tsInt <= 0 || (time() - $tsInt) > 7200) {
        unset($_SESSION['grade_checkout_ready'][$id]);
    }
}
foreach ($_SESSION['grade_checkout_tokens'] as $token => $meta) {
    $expiresAt = (int) ($meta['expires_at'] ?? 0);
    if ($expiresAt <= 0 || time() > $expiresAt) {
        unset($_SESSION['grade_checkout_tokens'][$token]);
    }
}
$_SESSION['grade_checkout_ready'][$diariaId] = time();
$checkoutToken = bin2hex(random_bytes(16));
$_SESSION['grade_checkout_tokens'][$checkoutToken] = [
    'diaria_id' => $diariaId,
    'expires_at' => time() + 1800,
];

Helpers::json(['ok' => true, 'checkout_token' => $checkoutToken]);
