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
    'select=id,student_name,student_id,enrollment,guardian_cpf,guardian_email,paid_at&id=eq.' . urlencode($pendenciaId) . '&limit=1'
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

    $existingByNameResult = $client->select(
        'students',
        'select=id,name,enrollment,grade,class_name,active&name=eq.' . urlencode($studentName)
            . '&active=eq.true'
            . '&order=created_at.asc'
            . '&limit=50'
    );
    $existingByName = (($existingByNameResult['ok'] ?? false) && is_array($existingByNameResult['data'] ?? null))
        ? $existingByNameResult['data']
        : [];

    if ($grade < 6 || $grade > 8) {
        if (count($existingByName) === 1) {
            $studentRow = $existingByName[0];
        } elseif (count($existingByName) > 1) {
            Helpers::json([
                'ok' => false,
                'error' => 'Mais de um aluno com esse nome. Informe a série (6, 7 ou 8) para concluir o vínculo.',
            ], 422);
        } else {
            Helpers::json(['ok' => false, 'error' => 'Informe a série entre 6 e 8 para incluir o aluno.'], 422);
        }
    } else {
        $matchByGrade = array_values(array_filter($existingByName, static function ($row) use ($grade): bool {
            return (int) ($row['grade'] ?? 0) === $grade;
        }));

        if (count($matchByGrade) === 1) {
            $studentRow = $matchByGrade[0];
        } elseif (count($matchByGrade) > 1) {
            Helpers::json([
                'ok' => false,
                'error' => 'Há mais de um aluno ativo com esse nome e série. Use "Mesclar com existente".',
            ], 422);
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

$linkedPendenciaIds = [$pendenciaId];
$studentNameOriginal = trim((string) ($pendenciaRow['student_name'] ?? ''));
$guardianCpfRaw = trim((string) ($pendenciaRow['guardian_cpf'] ?? ''));
$guardianCpfDigits = preg_replace('/\D+/', '', $guardianCpfRaw) ?? '';
$guardianEmail = trim((string) ($pendenciaRow['guardian_email'] ?? ''));
$relatedQueries = [];

if ($studentNameOriginal !== '') {
    if ($guardianCpfDigits !== '') {
        $relatedQueries[] = 'select=id'
            . '&student_name=eq.' . urlencode($studentNameOriginal)
            . '&guardian_cpf=ilike.' . urlencode('*' . $guardianCpfDigits . '*')
            . '&paid_at=is.null'
            . '&student_id=is.null'
            . '&id=neq.' . urlencode($pendenciaId)
            . '&limit=200';
        if ($guardianCpfRaw !== '' && $guardianCpfRaw !== $guardianCpfDigits) {
            $relatedQueries[] = 'select=id'
                . '&student_name=eq.' . urlencode($studentNameOriginal)
                . '&guardian_cpf=eq.' . urlencode($guardianCpfRaw)
                . '&paid_at=is.null'
                . '&student_id=is.null'
                . '&id=neq.' . urlencode($pendenciaId)
                . '&limit=200';
        }
    }
    if ($guardianEmail !== '') {
        $relatedQueries[] = 'select=id'
            . '&student_name=eq.' . urlencode($studentNameOriginal)
            . '&guardian_email=eq.' . urlencode($guardianEmail)
            . '&paid_at=is.null'
            . '&student_id=is.null'
            . '&id=neq.' . urlencode($pendenciaId)
            . '&limit=200';
    }
}

$relatedIds = [];
foreach ($relatedQueries as $query) {
    $relatedResult = $client->select('pendencia_de_cadastro', $query);
    if (!($relatedResult['ok'] ?? false) || empty($relatedResult['data'])) {
        continue;
    }
    foreach ($relatedResult['data'] as $relatedRow) {
        $relatedId = trim((string) ($relatedRow['id'] ?? ''));
        if ($relatedId !== '') {
            $relatedIds[$relatedId] = true;
        }
    }
}

if (!empty($relatedIds)) {
    $bulkPayload = [
        'student_id' => $studentRow['id'],
        'student_name' => $studentNameFinal,
        'enrollment' => $enrollmentFinal,
    ];
    foreach (array_keys($relatedIds) as $relatedId) {
        $bulkUpdate = $client->update(
            'pendencia_de_cadastro',
            'id=eq.' . urlencode($relatedId),
            $bulkPayload
        );
        if ($bulkUpdate['ok'] ?? false) {
            $linkedPendenciaIds[] = $relatedId;
        }
    }
}

Helpers::json([
    'ok' => true,
    'action' => $action,
    'created_student' => $createdStudent,
    'pendencia_id' => $pendenciaId,
    'linked_pendencia_ids' => array_values(array_unique($linkedPendenciaIds)),
    'student' => [
        'id' => (string) ($studentRow['id'] ?? ''),
        'name' => $studentNameFinal,
        'enrollment' => $enrollmentFinal,
        'grade' => isset($studentRow['grade']) ? (int) $studentRow['grade'] : null,
        'class_name' => $studentRow['class_name'] ?? null,
    ],
]);
