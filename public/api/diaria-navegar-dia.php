<?php
require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';
date_default_timezone_set('America/Sao_Paulo');

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

Helpers::requirePost();
$user = Helpers::requireAuth();
$payload = json_decode(file_get_contents('php://input'), true);

$diariaId = isset($payload['diaria_id']) ? trim((string) $payload['diaria_id']) : '';
$targetDia = isset($payload['target_dia_semana']) ? (int) $payload['target_dia_semana'] : 0;

if ($diariaId === '') {
    Helpers::json(['ok' => false, 'error' => 'Diária não informada.'], 422);
}
if ($targetDia < 1 || $targetDia > 5) {
    Helpers::json(['ok' => false, 'error' => 'Dia da semana inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$guardianResult = $client->select('guardians', 'select=id,student_id&id=eq.' . rawurlencode((string) $user['id']) . '&limit=1');
if (!$guardianResult['ok'] || empty($guardianResult['data'][0])) {
    Helpers::json(['ok' => false, 'error' => 'Responsável não encontrado.'], 404);
}

$guardian = $guardianResult['data'][0];
$guardianId = (string) ($guardian['id'] ?? '');
$studentId = (string) ($guardian['student_id'] ?? '');
if ($guardianId === '' || $studentId === '') {
    Helpers::json(['ok' => false, 'error' => 'Dados de responsável/aluno incompletos.'], 422);
}

$diariaResult = $client->select(
    'diaria',
    'select=id,data_diaria'
    . '&id=eq.' . rawurlencode($diariaId)
    . '&guardian_id=eq.' . rawurlencode($guardianId)
    . '&limit=1'
);
if (!$diariaResult['ok'] || empty($diariaResult['data'][0])) {
    Helpers::json(['ok' => false, 'error' => 'Diária atual não encontrada.'], 404);
}

$nextBusinessDay = static function (\DateTimeImmutable $date): \DateTimeImmutable {
    $candidate = $date->modify('+1 day');
    while ((int) $candidate->format('N') >= 6) {
        $candidate = $candidate->modify('+1 day');
    }
    return $candidate;
};

$nextOccurrenceFromDate = static function (\DateTimeImmutable $baseDate, int $targetWeekday): \DateTimeImmutable {
    $candidate = $baseDate;
    while ((int) $candidate->format('N') !== $targetWeekday) {
        $candidate = $candidate->modify('+1 day');
    }
    return $candidate;
};

$now = new \DateTimeImmutable('now');
$today = new \DateTimeImmutable($now->format('Y-m-d'));
$diaAtualReal = (int) $today->format('N'); // 1..7
$horaAtual = (int) $now->format('H');
$proximaSemana = false;

if ($diaAtualReal >= 6) {
    // Sábado/Domingo: todos os botões úteis apontam para a próxima ocorrência do dia clicado.
    $novaDataDt = $nextOccurrenceFromDate($today->modify('+1 day'), $targetDia);
    $proximaSemana = true;
} elseif ($targetDia === $diaAtualReal) {
    // Mesmo dia: antes das 16h fica hoje; após as 16h vai para a próxima ocorrência desse mesmo dia útil.
    if ($horaAtual < 16) {
        $novaDataDt = $today;
    } else {
        $novaDataDt = $nextOccurrenceFromDate($today->modify('+1 day'), $targetDia);
        $proximaSemana = true;
    }
} elseif ($targetDia > $diaAtualReal) {
    // Dia ainda não ocorreu nesta semana: usa a ocorrência desta semana.
    $novaDataDt = $nextOccurrenceFromDate($today, $targetDia);
} else {
    // Dia já passou na semana corrente: usa a próxima ocorrência (semana seguinte) e avisa.
    $novaDataDt = $nextOccurrenceFromDate($today->modify('+1 day'), $targetDia);
    $proximaSemana = true;
}

// Segurança adicional: se cair no fim de semana, avança até o próximo dia útil.
while ((int) $novaDataDt->format('N') >= 6) {
    $novaDataDt = $nextBusinessDay($novaDataDt);
}

$novaData = $novaDataDt->format('Y-m-d');

$existing = $client->select(
    'diaria',
    'select=id'
    . '&guardian_id=eq.' . rawurlencode($guardianId)
    . '&student_id=eq.' . rawurlencode($studentId)
    . '&data_diaria=eq.' . rawurlencode($novaData)
    . '&status_pagamento=eq.PENDENTE'
    . '&order=created_at.desc'
    . '&limit=1'
);

if (($existing['ok'] ?? false) && !empty($existing['data'][0]['id'])) {
    $destinoId = (string) $existing['data'][0]['id'];
} else {
    $insert = $client->insert('diaria', [[
        'guardian_id' => $guardianId,
        'student_id' => $studentId,
        'data_diaria' => $novaData,
        'grade_oficina_modular_ok' => false,
        'status_pagamento' => 'PENDENTE',
        'grade_travada' => false,
    ]]);
    if (!$insert['ok'] || empty($insert['data'][0]['id'])) {
        Helpers::json(['ok' => false, 'error' => 'Não foi possível preparar a diária para o dia selecionado.'], 500);
    }
    $destinoId = (string) $insert['data'][0]['id'];
}

Helpers::json([
    'ok' => true,
    'redirect_url' => '/diaria-grade-oficina-modular.php?diariaId=' . rawurlencode($destinoId)
        . ($proximaSemana ? '&proxima_semana=1' : ''),
    'target_dia_semana' => $targetDia,
    'target_data_diaria' => $novaData,
    'proxima_semana' => $proximaSemana,
]);
