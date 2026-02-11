<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\Mailer;
use App\SupabaseClient;

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);

$studentName = trim($payload['student_name'] ?? '');
$cpf = trim($payload['cpf'] ?? '');
$email = trim($payload['email'] ?? '');
$password = $payload['password'] ?? '';
$passwordConfirm = $payload['password_confirm'] ?? '';

if ($studentName === '' || $cpf === '' || $email === '' || $password === '') {
    Helpers::json(['ok' => false, 'error' => 'Preencha nome do aluno, CPF, e-mail e senha.'], 422);
}

if ($password !== $passwordConfirm) {
    Helpers::json(['ok' => false, 'error' => 'As senhas não conferem.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$studentResult = $client->select('students', 'select=id,name,grade&name=eq.' . urlencode($studentName) . '&grade=in.(6,7,8)&active=eq.true');

if (!$studentResult['ok'] || empty($studentResult['data'])) {
    Helpers::json(['ok' => false, 'error' => 'Aluno não encontrado.'], 404);
}

$student = $studentResult['data'][0];

$cpfDigits = preg_replace('/\D+/', '', $cpf) ?? '';
if (strlen($cpfDigits) !== 11) {
    Helpers::json(['ok' => false, 'error' => 'CPF inválido.'], 422);
}
$guardianExists = $client->select(
    'guardians',
    'select=id&student_id=eq.' . urlencode($student['id']) . '&parent_document=eq.' . urlencode($cpfDigits)
);
if ($guardianExists['ok'] && !empty($guardianExists['data'])) {
    Helpers::json(['ok' => false, 'error' => 'CPF ja cadastrado para este aluno.'], 409);
}

$exists = $client->select('guardians', 'select=id&email=eq.' . urlencode($email));
if ($exists['ok'] && !empty($exists['data'])) {
    Helpers::json(['ok' => false, 'error' => 'E-mail ja cadastrado.'], 409);
}

$guardian = $client->insert('guardians', [[
    'student_id' => $student['id'],
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'parent_document' => $cpfDigits,
]]);

if (!$guardian['ok']) {
    Helpers::json(['ok' => false, 'error' => 'Erro ao criar cadastro.'], 500);
}

$guardianId = $guardian['data'][0]['id'];
$token = bin2hex(random_bytes(16));
$expiresAt = date('c', strtotime('+24 hours'));
$client->insert('verification_tokens', [[
    'guardian_id' => $guardianId,
    'token' => $token,
    'expires_at' => $expiresAt,
]]);

$verifyLink = Helpers::baseUrl() . '/verify.php?token=' . $token;

$mailer = new Mailer();
$mailResult = $mailer->send(
    $email,
    'Confirme seu acesso - Diárias Village',
    '<p>Ola! Clique no link para confirmar seu e-mail:</p><p><a href="' . $verifyLink . '">' . $verifyLink . '</a></p>'
);

if (!$mailResult['ok']) {
    Helpers::json(['ok' => false, 'error' => 'Cadastro criado, mas falha no envio do e-mail.'], 500);
}

Helpers::json(['ok' => true]);
