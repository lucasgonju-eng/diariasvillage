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
use App\SupabaseClient;

Helpers::requirePost();
$user = Helpers::requireAuth();
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = [];
}

$parentName = trim((string) ($payload['parent_name'] ?? ''));
$email = strtolower(trim((string) ($payload['email'] ?? '')));
$parentPhone = trim((string) ($payload['parent_phone'] ?? ''));
$parentDocument = preg_replace('/\D+/', '', (string) ($payload['parent_document'] ?? '')) ?? '';

if ($parentName === '' || $email === '') {
    Helpers::json(['ok' => false, 'error' => 'Informe nome e e-mail do responsável.'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Helpers::json(['ok' => false, 'error' => 'E-mail inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$studentId = trim((string) ($user['student_id'] ?? ''));
if ($studentId === '') {
    $userId = trim((string) ($user['id'] ?? ''));
    if ($userId !== '') {
        $currentResult = $client->select(
            'guardians',
            'select=student_id&id=eq.' . urlencode($userId) . '&limit=1'
        );
        $current = $currentResult['data'][0] ?? null;
        $studentId = trim((string) ($current['student_id'] ?? ''));
    }
}
if ($studentId === '') {
    $userEmail = strtolower(trim((string) ($user['email'] ?? '')));
    if ($userEmail !== '') {
        $currentByEmail = $client->select(
            'guardians',
            'select=student_id&email=eq.' . urlencode($userEmail) . '&limit=1'
        );
        $current = $currentByEmail['data'][0] ?? null;
        $studentId = trim((string) ($current['student_id'] ?? ''));
    }
}
if ($studentId === '') {
    Helpers::json(['ok' => false, 'error' => 'Aluno vinculado não encontrado para esta conta.'], 422);
}

$emailResult = $client->select('guardians', 'select=id,student_id&email=eq.' . urlencode($email) . '&limit=1');
$emailGuardian = $emailResult['data'][0] ?? null;
if ($emailGuardian) {
    $emailStudentId = trim((string) ($emailGuardian['student_id'] ?? ''));
    if ($emailStudentId !== $studentId) {
        Helpers::json(['ok' => false, 'error' => 'Este e-mail já está vinculado a outro aluno.'], 409);
    }
    Helpers::json(['ok' => false, 'error' => 'Este e-mail já está cadastrado para este aluno.'], 409);
}
if ($parentDocument !== '') {
    $docResult = $client->select(
        'guardians',
        'select=id,student_id&parent_document=eq.' . urlencode($parentDocument) . '&limit=1'
    );
    $docGuardian = $docResult['data'][0] ?? null;
    if ($docGuardian) {
        $docStudentId = trim((string) ($docGuardian['student_id'] ?? ''));
        if ($docStudentId !== $studentId) {
            Helpers::json(['ok' => false, 'error' => 'Este CPF/CNPJ já está vinculado a outro aluno.'], 409);
        }
        Helpers::json(['ok' => false, 'error' => 'Este CPF/CNPJ já está cadastrado para este aluno.'], 409);
    }
}

$insert = $client->insert('guardians', [[
    'student_id' => $studentId,
    'email' => $email,
    'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
    'parent_name' => $parentName,
    'parent_phone' => $parentPhone !== '' ? $parentPhone : null,
    'parent_document' => $parentDocument !== '' ? $parentDocument : null,
    'verified_at' => date('c'),
]]);

if (!($insert['ok'] ?? false) || empty($insert['data'][0])) {
    $detail = trim((string) ($insert['error'] ?? ''));
    if ($detail !== '') {
        Helpers::json(['ok' => false, 'error' => 'Falha ao adicionar responsável: ' . $detail], 500);
    }
    Helpers::json(['ok' => false, 'error' => 'Falha ao adicionar responsável.'], 500);
}

Helpers::json(['ok' => true]);
