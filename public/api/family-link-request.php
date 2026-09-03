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

use App\GuardianAccountIdentity;
use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

Helpers::requirePost();
$user = Helpers::requireAuth();
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = [];
}

$csrfToken = trim((string) ($payload['csrf_token'] ?? ''));
$expectedCsrfToken = trim((string) ($_SESSION['family_link_request_csrf'] ?? ''));
if (
    $csrfToken === ''
    || $expectedCsrfToken === ''
    || !hash_equals($expectedCsrfToken, $csrfToken)
) {
    Helpers::json(['ok' => false, 'error' => 'Solicitação expirada. Recarregue a página.'], 403);
}

$enrollment = mb_strtoupper(trim((string) ($payload['enrollment'] ?? '')), 'UTF-8');
if ($enrollment === '' || strlen($enrollment) > 80) {
    Helpers::json(['ok' => false, 'error' => 'Informe a matrícula do outro filho.'], 422);
}

$authUserId = trim((string) ($user['auth_user_id'] ?? ''));
$requesterGuardianId = trim((string) ($user['id'] ?? ''));
$sourceStudentId = trim((string) ($user['student_id'] ?? ''));
$uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
if (
    !preg_match($uuidPattern, $authUserId)
    || !preg_match($uuidPattern, $requesterGuardianId)
    || !preg_match($uuidPattern, $sourceStudentId)
) {
    Helpers::json([
        'ok' => false,
        'error' => 'A conta precisa estar vinculada ao Supabase Auth para solicitar outro filho.',
    ], 409);
}

$client = new SupabaseClient(new HttpClient());
$accountResult = $client->selectAll(
    'guardians',
    'select=*&auth_user_id=eq.' . rawurlencode($authUserId) . '&order=id.asc'
);
$accountRows = (($accountResult['ok'] ?? false) && is_array($accountResult['data'] ?? null))
    ? array_values(array_filter($accountResult['data'], 'is_array'))
    : [];
$identity = GuardianAccountIdentity::analyze($accountRows, $requesterGuardianId);
if (
    !($identity['ok'] ?? false)
    || ($identity['mode'] ?? '') !== 'supabase_auth'
    || !hash_equals($authUserId, (string) ($identity['auth_user_id'] ?? ''))
) {
    Helpers::json([
        'ok' => false,
        'error' => 'A identidade da conta precisa de revisão antes de solicitar outro filho.',
    ], 409);
}

$accountStudentIds = [];
foreach ($accountRows as $guardian) {
    $accountStudentIds[trim((string) ($guardian['student_id'] ?? ''))] = true;
}
if (!isset($accountStudentIds[$sourceStudentId])) {
    Helpers::json(['ok' => false, 'error' => 'O vínculo atual da conta mudou. Entre novamente.'], 409);
}

$pendingResult = $client->select(
    'family_link_requests',
    'select=id,requested_enrollment&requester_auth_user_id=eq.' . rawurlencode($authUserId)
        . '&status=eq.PENDING&order=requested_at.desc&limit=11'
);
if (!($pendingResult['ok'] ?? false)) {
    Helpers::json(['ok' => false, 'error' => 'Não foi possível conferir solicitações anteriores.'], 503);
}
$pendingRows = is_array($pendingResult['data'] ?? null) ? $pendingResult['data'] : [];
foreach ($pendingRows as $pendingRow) {
    if (
        is_array($pendingRow)
        && mb_strtoupper(trim((string) ($pendingRow['requested_enrollment'] ?? '')), 'UTF-8') === $enrollment
    ) {
        unset($_SESSION['family_link_request_csrf']);
        Helpers::json([
            'ok' => true,
            'message' => 'Solicitação recebida. A secretaria verificará a matrícula e o vínculo familiar.',
        ]);
    }
}
if (count($pendingRows) >= 10) {
    Helpers::json([
        'ok' => false,
        'error' => 'Há muitas solicitações pendentes nesta conta. Aguarde a análise da secretaria.',
    ], 429);
}

$insert = $client->insert('family_link_requests', [[
    'requester_auth_user_id' => $authUserId,
    'requester_guardian_id' => $requesterGuardianId,
    'source_student_id' => $sourceStudentId,
    'requested_enrollment' => $enrollment,
    'status' => 'PENDING',
]]);
if (!($insert['ok'] ?? false) || empty($insert['data'][0]['id'])) {
    Helpers::json([
        'ok' => false,
        'error' => 'Não foi possível registrar a solicitação. Tente novamente.',
    ], 503);
}

unset($_SESSION['family_link_request_csrf']);
Helpers::json([
    'ok' => true,
    'message' => 'Solicitação recebida. A secretaria verificará a matrícula e o vínculo familiar.',
]);
