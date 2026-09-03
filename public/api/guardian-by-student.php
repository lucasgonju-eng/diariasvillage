<?php
require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

Helpers::requireAdminRole(\App\AdminAuth::ROLE_ADMIN);

$studentId = trim((string) ($_GET['student_id'] ?? ''));
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $studentId)) {
    Helpers::json(['ok' => false, 'error' => 'Aluno inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$studentResult = $client->select('students', 'select=id,name&id=eq.' . urlencode($studentId) . '&limit=1');
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
