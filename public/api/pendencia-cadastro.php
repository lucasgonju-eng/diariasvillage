<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\Mailer;
use App\SupabaseClient;

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);

$studentName = trim($payload['student_name'] ?? '');
$guardianName = trim($payload['guardian_name'] ?? '');
$guardianCpf = trim($payload['guardian_cpf'] ?? '');
$guardianEmail = trim($payload['guardian_email'] ?? '');

if ($studentName === '' || $guardianName === '' || $guardianCpf === '' || $guardianEmail === '') {
    Helpers::json(['ok' => false, 'error' => 'Preencha nome do aluno, responsavel, CPF e e-mail.'], 422);
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

$pendenciaId = $insert['data'][0]['id'] ?? null;
if (!$pendenciaId) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao registrar pendencia.'], 500);
}

$token = bin2hex(random_bytes(16));
$expiresAt = date('c', strtotime('+24 hours'));
$client->insert('pendencia_tokens', [[
    'pendencia_id' => $pendenciaId,
    'token' => $token,
    'expires_at' => $expiresAt,
]]);

$verifyLink = Helpers::baseUrl() . '/pendencia-verify.php?token=' . $token;

$mailer = new Mailer();
$mailer->send(
    $guardianEmail,
    'Confirme seu e-mail - Diárias Village',
    '<p>Olá! Clique no link para confirmar seu e-mail e garantir a diária planejada:</p>'
    . '<p><a href="' . $verifyLink . '">' . $verifyLink . '</a></p>'
);

Helpers::json(['ok' => true]);
