<?php
require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);
$pendenciaId = trim((string) ($payload['pendencia_id'] ?? ''));
$action = trim((string) ($payload['action'] ?? ''));

if ($pendenciaId === '') {
    Helpers::json(['ok' => false, 'error' => 'Pendência inválida.'], 422);
}

if (!in_array($action, ['link_existing', 'create_student'], true)) {
    Helpers::json(['ok' => false, 'error' => 'Ação inválida.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$pendenciaResult = $client->select(
    'pendencia_de_cadastro',
    'select=id,student_name,student_id,enrollment&id=eq.' . urlencode($pendenciaId) . '&limit=1'
);

if (!($pendenciaResult['ok'] ?? false) || empty($pendenciaResult['data'])) {
    Helpers::json(['ok' => false, 'error' => 'Pendência não encontrada.'], 404);
}

$pendenciaRow = $pendenciaResult['data'][0];
$studentRow = null;
$createdStudent = false;

if ($action === 'link_existing') {
    $studentId = trim((string) ($payload['student_id'] ?? ''));
    if ($studentId === '') {
        Helpers::json(['ok' => false, 'error' => 'Selecione um aluno existente para mesclar.'], 422);
    }

    $studentResult = $client->select(
        'students',
        'select=id,name,enrollment,grade,class_name,active&id=eq.' . urlencode($studentId) . '&limit=1'
    );
    if (!($studentResult['ok'] ?? false) || empty($studentResult['data'])) {
        Helpers::json(['ok' => false, 'error' => 'Aluno não encontrado no banco.'], 404);
    }
    $studentRow = $studentResult['data'][0];
    if (array_key_exists('active', $studentRow) && $studentRow['active'] === false) {
        Helpers::json(['ok' => false, 'error' => 'O aluno selecionado está inativo.'], 422);
    }
}

if ($action === 'create_student') {
    $studentName = trim((string) ($payload['student_name'] ?? ($pendenciaRow['student_name'] ?? '')));
    $gradeRaw = preg_replace('/\D+/', '', (string) ($payload['grade'] ?? ''));
    $grade = (int) ($gradeRaw !== '' ? $gradeRaw : 0);
    $className = trim((string) ($payload['class_name'] ?? ''));
    $enrollment = trim((string) ($payload['enrollment'] ?? ''));

    if ($studentName === '') {
        Helpers::json(['ok' => false, 'error' => 'Informe o nome do aluno para incluir no banco.'], 422);
    }
    if ($grade < 6 || $grade > 8) {
        Helpers::json(['ok' => false, 'error' => 'Informe a série entre 6 e 8 para incluir o aluno.'], 422);
    }

    $existingResult = $client->select(
        'students',
        'select=id,name,enrollment,grade,class_name,active&name=eq.' . urlencode($studentName)
            . '&grade=eq.' . $grade
            . '&active=eq.true'
            . '&limit=1'
    );

    if (($existingResult['ok'] ?? false) && !empty($existingResult['data'])) {
        $studentRow = $existingResult['data'][0];
    } else {
        $insertResult = $client->insert('students', [[
            'name' => $studentName,
            'grade' => $grade,
            'class_name' => $className !== '' ? $className : null,
            'enrollment' => $enrollment !== '' ? $enrollment : null,
            'active' => true,
        ]]);

        if (!($insertResult['ok'] ?? false) || empty($insertResult['data'])) {
            Helpers::json(['ok' => false, 'error' => 'Falha ao incluir aluno no banco.'], 500);
        }
        $studentRow = $insertResult['data'][0];
        $createdStudent = true;
    }
}

if (!$studentRow || empty($studentRow['id'])) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao preparar aluno para a pendência.'], 500);
}

$studentNameFinal = trim((string) ($studentRow['name'] ?? ($pendenciaRow['student_name'] ?? '')));
$enrollmentFinal = $studentRow['enrollment'] ?? null;

$updateResult = $client->update(
    'pendencia_de_cadastro',
    'id=eq.' . urlencode($pendenciaId),
    [
        'student_id' => $studentRow['id'],
        'student_name' => $studentNameFinal,
        'enrollment' => $enrollmentFinal,
    ]
);

if (!($updateResult['ok'] ?? false)) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao atualizar pendência com o aluno selecionado.'], 500);
}

Helpers::json([
    'ok' => true,
    'action' => $action,
    'created_student' => $createdStudent,
    'pendencia_id' => $pendenciaId,
    'student' => [
        'id' => (string) ($studentRow['id'] ?? ''),
        'name' => $studentNameFinal,
        'enrollment' => $enrollmentFinal,
        'grade' => isset($studentRow['grade']) ? (int) $studentRow['grade'] : null,
        'class_name' => $studentRow['class_name'] ?? null,
    ],
]);
