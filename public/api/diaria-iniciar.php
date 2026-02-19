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

Helpers::requirePost();
$user = Helpers::requireAuth();

$payload = json_decode(file_get_contents('php://input'), true);
$date = isset($payload['date']) ? trim((string) $payload['date']) : '';

if ($date === '') {
    Helpers::json(['ok' => false, 'error' => 'Selecione a data da diária.'], 422);
}

$dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
if (!$dt instanceof \DateTimeImmutable || $dt->format('Y-m-d') !== $date) {
    Helpers::json(['ok' => false, 'error' => 'Data inválida.'], 422);
}

$today = date('Y-m-d');
$hour = (int) date('H');
if ($date === $today && $hour >= 16) {
    Helpers::json([
        'ok' => false,
        'error' => 'Compras para hoje encerradas após as 16h. Escolha uma data futura.',
    ], 422);
}

$client = new SupabaseClient(new HttpClient());
$guardian = $client->select('guardians', 'select=*&id=eq.' . rawurlencode((string) $user['id']) . '&limit=1');
if (!$guardian['ok'] || empty($guardian['data'][0])) {
    Helpers::json(['ok' => false, 'error' => 'Responsável não encontrado.'], 404);
}

$guardianRow = $guardian['data'][0];
$guardianId = (string) ($guardianRow['id'] ?? '');
$studentId = (string) ($guardianRow['student_id'] ?? '');
if ($guardianId === '' || $studentId === '') {
    Helpers::json(['ok' => false, 'error' => 'Dados de responsável/aluno incompletos.'], 422);
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
        Helpers::json(['ok' => false, 'error' => 'Não foi possível iniciar a diária.'], 500);
    }

    $diaria = $insert['data'][0];
}

$diariaId = (string) ($diaria['id'] ?? '');
if ($diariaId === '') {
    Helpers::json(['ok' => false, 'error' => 'Diária inválida.'], 500);
}

Helpers::json([
    'ok' => true,
    'diaria_id' => $diariaId,
    'redirect_url' => '/diaria-grade-oficina-modular.php?diariaId=' . rawurlencode($diariaId),
]);
