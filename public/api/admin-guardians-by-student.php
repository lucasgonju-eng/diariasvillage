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

$studentId = trim((string) ($_GET['student_id'] ?? ''));
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $studentId)) {
    Helpers::json(['ok' => false, 'error' => 'Aluno inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$studentResult = $client->select(
    'students',
    'select=id,name&id=eq.' . rawurlencode($studentId) . '&limit=1'
);
$student = $studentResult['data'][0] ?? null;
if (!$student) {
    Helpers::json(['ok' => false, 'error' => 'Aluno não encontrado.'], 404);
}

$guardianResult = $client->select(
    'guardians',
    'select=id,parent_name,parent_phone,parent_document,email,created_at&student_id=eq.' . rawurlencode($studentId)
    . '&order=created_at.desc&limit=100'
);
$guardians = is_array($guardianResult['data'] ?? null) ? $guardianResult['data'] : [];

Helpers::json([
    'ok' => true,
    'guardians' => $guardians,
]);
