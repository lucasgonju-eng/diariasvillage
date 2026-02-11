<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);
$pendenciaId = trim($payload['id'] ?? '');

if ($pendenciaId === '') {
    Helpers::json(['ok' => false, 'error' => 'ID inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$pendenciaResult = $client->select(
    'pendencia_de_cadastro',
    'select=id,paid_at&' . 'id=eq.' . urlencode($pendenciaId)
);
if (!$pendenciaResult['ok'] || empty($pendenciaResult['data'])) {
    Helpers::json(['ok' => false, 'error' => 'Pendência não encontrada.'], 404);
}

if (!empty($pendenciaResult['data'][0]['paid_at'])) {
    Helpers::json(['ok' => true, 'paid_at' => $pendenciaResult['data'][0]['paid_at']]);
}

$paidAt = date('c');
$update = $client->update('pendencia_de_cadastro', 'id=eq.' . urlencode($pendenciaId), [
    'paid_at' => $paidAt,
]);
if (!$update['ok']) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao dar baixa.'], 500);
}

Helpers::json(['ok' => true, 'paid_at' => $paidAt]);
