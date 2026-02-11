<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

$studentName = trim($_GET['name'] ?? '');
if ($studentName === '') {
    Helpers::json(['ok' => false, 'error' => 'Aluno inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$studentResult = $client->select('students', 'select=id,name&name=eq.' . urlencode($studentName));
$student = $studentResult['data'][0] ?? null;
if (!$student) {
    Helpers::json(['ok' => false, 'error' => 'Aluno não encontrado.'], 404);
}

$guardianResult = $client->select(
    'guardians',
    'select=parent_name,parent_phone,parent_document,email&student_id=eq.' . $student['id']
    . '&order=created_at.desc&limit=1'
);
$guardian = $guardianResult['data'][0] ?? null;

Helpers::json([
    'ok' => true,
    'guardian' => $guardian,
]);
