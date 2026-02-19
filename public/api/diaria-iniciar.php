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
date_default_timezone_set('America/Sao_Paulo');

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    Helpers::json(['ok' => false, 'error' => 'Método inválido.'], 405);
}

$isGetRequest = $method === 'GET';
$user = $isGetRequest ? Helpers::requireAuthWeb() : Helpers::requireAuth();

$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
$accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
$isJsonRequest = !$isGetRequest && (str_contains($contentType, 'application/json') || str_contains($accept, 'application/json'));
$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput ?: 'null', true);
if (!is_array($payload)) {
    $payload = [];
}
$date = isset($payload['date']) ? trim((string) $payload['date']) : '';
if ($date === '' && isset($_POST['date'])) {
    $date = trim((string) $_POST['date']);
}
if ($date === '' && isset($_GET['date'])) {
    $date = trim((string) $_GET['date']);
}

$respondError = static function (string $message, int $status = 422) use ($isJsonRequest): void {
    if ($isJsonRequest) {
        Helpers::json(['ok' => false, 'error' => $message], $status);
    }
    $_SESSION['dashboard_error'] = $message;
    header('Location: /dashboard.php');
    exit;
};

if ($date === '') {
    $respondError('Selecione a data da diária.', 422);
}

$dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
if (!$dt instanceof \DateTimeImmutable || $dt->format('Y-m-d') !== $date) {
    $respondError('Data inválida.', 422);
}

$today = date('Y-m-d');
$hour = (int) date('H');
if ($date === $today && $hour >= 16) {
    $respondError('Compras para hoje encerradas após as 16h. Escolha uma data futura.', 422);
}

$client = new SupabaseClient(new HttpClient());
$guardian = $client->select('guardians', 'select=*&id=eq.' . rawurlencode((string) $user['id']) . '&limit=1');
if (!$guardian['ok'] || empty($guardian['data'][0])) {
    $respondError('Responsável não encontrado.', 404);
}

$guardianRow = $guardian['data'][0];
$guardianId = (string) ($guardianRow['id'] ?? '');
$studentId = (string) ($guardianRow['student_id'] ?? '');
if ($guardianId === '' || $studentId === '') {
    $respondError('Dados de responsável/aluno incompletos.', 422);
}

$query = 'select=*'
    . '&guardian_id=eq.' . rawurlencode($guardianId)
    . '&student_id=eq.' . rawurlencode($studentId)
    . '&data_diaria=eq.' . rawurlencode($date)
    . '&order=created_at.desc'
    . '&limit=1';
$existing = $client->select('diaria', $query);

if ($existing['ok'] && !empty($existing['data'][0])) {
    $diaria = $existing['data'][0];
} else {
    $insert = $client->insert('diaria', [[
        'guardian_id' => $guardianId,
        'student_id' => $studentId,
        'data_diaria' => $date,
        'grade_oficina_modular_ok' => false,
    ]]);

    if (!$insert['ok'] || empty($insert['data'][0])) {
        $respondError('Não foi possível iniciar a diária.', 500);
    }

    $diaria = $insert['data'][0];
}

$diariaId = (string) ($diaria['id'] ?? '');
if ($diariaId === '') {
    $respondError('Diária inválida.', 500);
}

$redirectUrl = '/diaria-grade-oficina-modular.php?diariaId=' . rawurlencode($diariaId);
if ($isJsonRequest) {
    Helpers::json([
        'ok' => true,
        'diaria_id' => $diariaId,
        'redirect_url' => $redirectUrl,
    ]);
}

header('Location: ' . $redirectUrl);
exit;
