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
$studentId = trim((string) ($payload['student_id'] ?? ''));
$guardianId = trim((string) ($payload['guardian_id'] ?? ''));
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $studentId)) {
    Helpers::json(['ok' => false, 'error' => 'Selecione um aluno válido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$studentResult = $client->select(
    'students',
    'select=id,name,enrollment&id=eq.' . rawurlencode($studentId) . '&limit=1'
);
$student = $studentResult['data'][0] ?? null;
if (!$student) {
    Helpers::json(['ok' => false, 'error' => 'Aluno não encontrado.'], 404);
}

$guardianResult = $client->select(
    'guardians',
    'select=*&student_id=eq.' . rawurlencode($studentId) . '&order=created_at.asc&limit=100'
);
$guardians = ($guardianResult['ok'] ?? false) && is_array($guardianResult['data'] ?? null)
    ? $guardianResult['data']
    : [];
if (!$guardians) {
    Helpers::json([
        'ok' => false,
        'error' => 'Responsável não encontrado para este aluno.',
        'code' => 'GUARDIAN_NOT_FOUND',
        'student' => [
            'id' => (string) ($student['id'] ?? ''),
            'name' => (string) ($student['name'] ?? ''),
        ],
    ], 404);
}

$maskEmail = static function (string $email): string {
    $parts = explode('@', trim($email), 2);
    if (count($parts) !== 2) {
        return '';
    }
    return substr($parts[0], 0, 2) . '***@' . $parts[1];
};
$maskDocument = static function (string $value): string {
    $digits = preg_replace('/\D+/', '', $value) ?? '';
    return strlen($digits) >= 4 ? ('***' . substr($digits, -4)) : 'Não informado';
};

if ($guardianId === '' && count($guardians) > 1) {
    $options = array_map(static function (array $row) use ($maskEmail, $maskDocument): array {
        return [
            'id' => (string) ($row['id'] ?? ''),
            'name' => (string) ($row['parent_name'] ?? 'Responsável'),
            'email_masked' => $maskEmail((string) ($row['email'] ?? '')),
            'document_masked' => $maskDocument((string) ($row['parent_document'] ?? '')),
        ];
    }, $guardians);
    Helpers::json([
        'ok' => false,
        'code' => 'GUARDIAN_SELECTION_REQUIRED',
        'error' => 'Selecione explicitamente o responsável.',
        'student' => [
            'id' => (string) ($student['id'] ?? ''),
            'name' => (string) ($student['name'] ?? ''),
            'enrollment' => (string) ($student['enrollment'] ?? ''),
        ],
        'guardians' => $options,
    ], 409);
}

$guardian = null;
if ($guardianId !== '') {
    foreach ($guardians as $candidate) {
        if ((string) ($candidate['id'] ?? '') === $guardianId) {
            $guardian = $candidate;
            break;
        }
    }
    if ($guardian === null) {
        Helpers::json([
            'ok' => false,
            'error' => 'O responsável selecionado não pertence a este aluno.',
        ], 422);
    }
} else {
    $guardian = $guardians[0];
}

$_SESSION['user'] = $guardian;
$_SESSION['admin_impersonating_student'] = (string) ($student['name'] ?? '');
$_SESSION['admin_impersonating_student_id'] = (string) ($student['id'] ?? '');
$_SESSION['admin_impersonating_guardian_id'] = (string) ($guardian['id'] ?? '');

Helpers::json([
    'ok' => true,
    'url' => '/dashboard.php?view_as=' . rawurlencode((string) ($student['enrollment'] ?? $student['name'] ?? '')),
]);
