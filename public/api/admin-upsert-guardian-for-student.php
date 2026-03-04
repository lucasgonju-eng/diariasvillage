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

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}
if (($_SESSION['admin_user'] ?? '') !== 'admin') {
    Helpers::json(['ok' => false, 'error' => 'Recurso disponível apenas para o admin principal.'], 403);
}

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);

$studentName = trim((string) ($payload['student_name'] ?? ''));
$parentName = trim((string) ($payload['parent_name'] ?? ''));
$email = trim((string) ($payload['email'] ?? ''));
$phone = trim((string) ($payload['parent_phone'] ?? ''));
$document = preg_replace('/\D+/', '', (string) ($payload['parent_document'] ?? '')) ?? '';
$forceCreate = (bool) ($payload['force_create'] ?? false);

if ($studentName === '' || $parentName === '') {
    Helpers::json(['ok' => false, 'error' => 'Informe aluno e nome do responsável.'], 422);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Helpers::json(['ok' => false, 'error' => 'E-mail inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$studentResult = $client->select('students', 'select=id,name&name=eq.' . urlencode($studentName) . '&limit=1');
$student = $studentResult['data'][0] ?? null;
if (!$student) {
    Helpers::json(['ok' => false, 'error' => 'Aluno não encontrado.'], 404);
}

$studentId = (string) ($student['id'] ?? '');
if ($studentId === '') {
    Helpers::json(['ok' => false, 'error' => 'Aluno inválido.'], 422);
}

if ($email === '') {
    $email = 'sem-email+' . preg_replace('/[^a-z0-9]/i', '', strtolower($studentId)) . '@diariasvillage.local';
}

$emailResult = $client->select('guardians', 'select=id,student_id&email=eq.' . urlencode($email) . '&limit=1');
$emailGuardian = $emailResult['data'][0] ?? null;
if ($emailGuardian && (string) ($emailGuardian['student_id'] ?? '') !== $studentId) {
    Helpers::json([
        'ok' => false,
        'error' => 'Este e-mail já está vinculado a outro aluno. Use outro e-mail para este responsável.',
    ], 409);
}
if ($forceCreate && $emailGuardian) {
    Helpers::json([
        'ok' => false,
        'error' => 'Este e-mail já está em uso. Para criar mais um responsável, use outro e-mail.',
    ], 409);
}

$guardianResult = $client->select(
    'guardians',
    'select=id&student_id=eq.' . urlencode($studentId) . '&order=created_at.desc&limit=1'
);
$guardian = $guardianResult['data'][0] ?? null;

$payloadDb = [
    'parent_name' => $parentName,
    'parent_phone' => $phone,
    'parent_document' => $document !== '' ? $document : null,
    'email' => $email,
];

if ($guardian && !$forceCreate) {
    $update = $client->update('guardians', 'id=eq.' . urlencode((string) $guardian['id']), $payloadDb);
    if (!($update['ok'] ?? false) || empty($update['data'][0])) {
        Helpers::json(['ok' => false, 'error' => 'Falha ao atualizar responsável.'], 500);
    }
    Helpers::json(['ok' => true, 'guardian' => $update['data'][0]]);
}

$insert = $client->insert('guardians', [[
    'student_id' => $studentId,
    'email' => $email,
    'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
    'parent_name' => $parentName,
    'parent_phone' => $phone !== '' ? $phone : null,
    'parent_document' => $document !== '' ? $document : null,
    'verified_at' => date('c'),
]]);
if (!($insert['ok'] ?? false) || empty($insert['data'][0])) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao criar responsável.'], 500);
}

Helpers::json(['ok' => true, 'guardian' => $insert['data'][0]]);
