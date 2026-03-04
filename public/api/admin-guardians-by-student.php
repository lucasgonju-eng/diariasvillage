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

$studentName = trim((string) ($_GET['name'] ?? ''));
if ($studentName === '') {
    Helpers::json(['ok' => false, 'error' => 'Aluno inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$studentResult = $client->select('students', 'select=id,name&name=eq.' . urlencode($studentName) . '&limit=1');
$student = $studentResult['data'][0] ?? null;
if (!$student) {
    Helpers::json(['ok' => false, 'error' => 'Aluno não encontrado.'], 404);
}

$studentId = (string) ($student['id'] ?? '');
$guardianResult = $client->select(
    'guardians',
    'select=id,parent_name,parent_phone,parent_document,email,created_at&student_id=eq.' . urlencode($studentId)
    . '&order=created_at.desc&limit=100'
);
$guardians = is_array($guardianResult['data'] ?? null) ? $guardianResult['data'] : [];

Helpers::json([
    'ok' => true,
    'guardians' => $guardians,
]);
