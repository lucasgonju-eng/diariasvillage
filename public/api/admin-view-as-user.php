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
$studentName = trim((string) ($payload['student_name'] ?? ''));
if ($studentName === '') {
    Helpers::json(['ok' => false, 'error' => 'Selecione um aluno válido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$studentResult = $client->select(
    'students',
    'select=id,name&name=eq.' . urlencode($studentName) . '&limit=1'
);
$student = $studentResult['data'][0] ?? null;
if (!$student) {
    Helpers::json(['ok' => false, 'error' => 'Aluno não encontrado.'], 404);
}

$guardianResult = $client->select(
    'guardians',
    'select=*&student_id=eq.' . urlencode((string) $student['id']) . '&order=created_at.desc&limit=1'
);
$guardian = $guardianResult['data'][0] ?? null;
if (!$guardian) {
    Helpers::json(['ok' => false, 'error' => 'Responsável não encontrado para este aluno.'], 404);
}

$_SESSION['user'] = $guardian;
$_SESSION['admin_impersonating_student'] = $student['name'] ?? null;

Helpers::json([
    'ok' => true,
    'url' => '/dashboard.php?view_as=' . urlencode((string) ($student['name'] ?? '')),
]);
