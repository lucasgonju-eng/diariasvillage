<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);

$studentName = trim($payload['student_name'] ?? '');
$guardianName = trim($payload['guardian_name'] ?? '');
$guardianCpf = trim($payload['guardian_cpf'] ?? '');
$guardianEmail = trim($payload['guardian_email'] ?? '');

if ($studentName === '' || $guardianName === '' || $guardianCpf === '') {
    Helpers::json(['ok' => false, 'error' => 'Preencha nome do aluno, responsavel e CPF.'], 422);
}

$cpfDigits = preg_replace('/\D+/', '', $guardianCpf) ?? '';
if (strlen($cpfDigits) !== 11) {
    Helpers::json(['ok' => false, 'error' => 'CPF invalido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$insert = $client->insert('pendencia_de_cadastro', [[
    'student_name' => $studentName,
    'guardian_name' => $guardianName,
    'guardian_cpf' => $cpfDigits,
    'guardian_email' => $guardianEmail ?: null,
]]);

if (!$insert['ok']) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao registrar pendencia.'], 500);
}

Helpers::json(['ok' => true]);
