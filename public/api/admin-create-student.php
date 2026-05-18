<?php
require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}
if (($_SESSION['admin_user'] ?? '') !== 'admin') {
    Helpers::json(['ok' => false, 'error' => 'Recurso disponível apenas para o admin principal.'], 403);
}

Helpers::requirePost();

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    Helpers::json(['ok' => false, 'error' => 'Dados inválidos.'], 422);
}

$studentName = trim((string) ($payload['student_name'] ?? ''));
$enrollment = trim((string) ($payload['enrollment'] ?? ''));
$grade = (int) preg_replace('/[^0-9]/', '', (string) ($payload['grade'] ?? ''));
$className = trim((string) ($payload['class_name'] ?? ''));
$birthDate = trim((string) ($payload['birth_date'] ?? ''));

$guardianName = trim((string) ($payload['guardian_name'] ?? ''));
$guardianEmail = trim((string) ($payload['guardian_email'] ?? ''));
$guardianPhone = trim((string) ($payload['guardian_phone'] ?? ''));
$guardianDocument = preg_replace('/\D+/', '', (string) ($payload['guardian_document'] ?? '')) ?? '';

if ($studentName === '') {
    Helpers::json(['ok' => false, 'error' => 'Informe o nome do aluno.'], 422);
}
if ($enrollment === '') {
    Helpers::json(['ok' => false, 'error' => 'Informe a matrícula do aluno.'], 422);
}
if ($grade < 6 || $grade > 8) {
    Helpers::json(['ok' => false, 'error' => 'Informe uma série válida: 6, 7 ou 8.'], 422);
}
if ($className === '') {
    Helpers::json(['ok' => false, 'error' => 'Informe a turma do aluno.'], 422);
}
if ($birthDate !== '') {
    $birth = \DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
    if (!$birth || $birth->format('Y-m-d') !== $birthDate) {
        Helpers::json(['ok' => false, 'error' => 'Data de nascimento inválida.'], 422);
    }
}
if ($guardianName === '' || $guardianEmail === '' || $guardianPhone === '' || $guardianDocument === '') {
    Helpers::json(['ok' => false, 'error' => 'Informe nome, e-mail, WhatsApp e CPF do responsável.'], 422);
}
if (!filter_var($guardianEmail, FILTER_VALIDATE_EMAIL)) {
    Helpers::json(['ok' => false, 'error' => 'E-mail do responsável inválido.'], 422);
}
if (strlen($guardianDocument) !== 11) {
    Helpers::json(['ok' => false, 'error' => 'CPF do responsável inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());

$duplicateEnrollment = $client->select(
    'students',
    'select=id,name,enrollment&enrollment=eq.' . urlencode($enrollment) . '&limit=1'
);
if (!($duplicateEnrollment['ok'] ?? false)) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao validar matrícula existente.'], 500);
}
if (!empty($duplicateEnrollment['data'])) {
    $existing = $duplicateEnrollment['data'][0] ?? [];
    Helpers::json([
        'ok' => false,
        'error' => 'Já existe aluno cadastrado com esta matrícula: ' . (string) ($existing['name'] ?? $enrollment),
    ], 409);
}

$duplicateName = $client->select(
    'students',
    'select=id,name,enrollment&name=eq.' . urlencode($studentName) . '&limit=1'
);
if (!($duplicateName['ok'] ?? false)) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao validar aluno existente.'], 500);
}
if (!empty($duplicateName['data'])) {
    $existing = $duplicateName['data'][0] ?? [];
    $existingEnrollment = trim((string) ($existing['enrollment'] ?? ''));
    Helpers::json([
        'ok' => false,
        'error' => 'Já existe aluno cadastrado com este nome' . ($existingEnrollment !== '' ? ' (matrícula ' . $existingEnrollment . ')' : '') . '.',
    ], 409);
}

$guardianEmailResult = $client->select(
    'guardians',
    'select=id,student_id,email&email=eq.' . urlencode($guardianEmail) . '&limit=1'
);
if (!($guardianEmailResult['ok'] ?? false)) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao validar e-mail do responsável.'], 500);
}
if (!empty($guardianEmailResult['data'])) {
    Helpers::json(['ok' => false, 'error' => 'Este e-mail já está vinculado a outro cadastro.'], 409);
}

$studentInsert = $client->insert('students', [[
    'name' => $studentName,
    'enrollment' => $enrollment,
    'grade' => $grade,
    'class_name' => $className,
    'birth_date' => $birthDate !== '' ? $birthDate : null,
    'active' => true,
]]);

if (!($studentInsert['ok'] ?? false) || empty($studentInsert['data'][0])) {
    $message = is_array($studentInsert['data'] ?? null)
        ? (string) (($studentInsert['data']['message'] ?? $studentInsert['data']['details'] ?? '') ?: '')
        : '';
    Helpers::json(['ok' => false, 'error' => $message ?: 'Falha ao criar aluno.'], 500);
}

$student = $studentInsert['data'][0];
$studentId = (string) ($student['id'] ?? '');
if ($studentId === '') {
    Helpers::json(['ok' => false, 'error' => 'Aluno criado, mas o identificador não retornou.'], 500);
}

$guardianInsert = $client->insert('guardians', [[
    'student_id' => $studentId,
    'email' => $guardianEmail,
    'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
    'parent_name' => $guardianName,
    'parent_phone' => $guardianPhone,
    'parent_document' => $guardianDocument,
]]);

if (!($guardianInsert['ok'] ?? false) || empty($guardianInsert['data'][0])) {
    $client->delete('students', 'id=eq.' . urlencode($studentId));
    Helpers::json([
        'ok' => false,
        'error' => 'Falha ao criar responsável. O cadastro do aluno não foi mantido; revise os dados e tente novamente.',
    ], 500);
}

Helpers::json([
    'ok' => true,
    'student' => $student,
    'guardian' => $guardianInsert['data'][0],
]);
