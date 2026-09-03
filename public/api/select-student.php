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

Helpers::requirePost();
$user = Helpers::requireAuth(true);
$studentId = trim((string) ($_POST['student_id'] ?? ''));
$csrfToken = trim((string) ($_POST['csrf_token'] ?? ''));
$expectedCsrfToken = trim((string) ($_SESSION['family_selection_csrf'] ?? ''));
if (
    $csrfToken === ''
    || $expectedCsrfToken === ''
    || !hash_equals($expectedCsrfToken, $csrfToken)
) {
    Helpers::json(['ok' => false, 'error' => 'Seleção expirada. Reabra a escolha de aluno.'], 403);
}
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $studentId)) {
    Helpers::json(['ok' => false, 'error' => 'Aluno inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$sessionAuthUserId = trim((string) ($user['auth_user_id'] ?? ''));
$currentGuardianId = trim((string) ($user['id'] ?? ''));
$currentResult = $client->select(
    'guardians',
    'select=id,auth_user_id&id=eq.' . rawurlencode($currentGuardianId) . '&limit=1'
);
$current = (($currentResult['ok'] ?? false) && is_array($currentResult['data'][0] ?? null))
    ? $currentResult['data'][0]
    : null;
$authUserId = is_array($current) ? trim((string) ($current['auth_user_id'] ?? '')) : '';
if (
    $currentGuardianId === ''
    || $sessionAuthUserId === ''
    || $authUserId === ''
    || !hash_equals($sessionAuthUserId, $authUserId)
) {
    $_SESSION = [];
    session_destroy();
    Helpers::json(['ok' => false, 'error' => 'A sessão familiar mudou. Entre novamente.'], 401);
}
if ($authUserId === '') {
    Helpers::json(['ok' => false, 'error' => 'Conta familiar não vinculada.'], 403);
}

$target = $client->select(
    'guardians',
    'select=*&auth_user_id=eq.' . rawurlencode($authUserId)
        . '&student_id=eq.' . rawurlencode($studentId)
        . '&limit=1'
);
if (!($target['ok'] ?? false) || !is_array($target['data'][0] ?? null)) {
    Helpers::json(['ok' => false, 'error' => 'Aluno não pertence a esta conta.'], 403);
}

$_SESSION['user'] = $target['data'][0];
$_SESSION['family_student_selection_required'] = false;
$_SESSION['family_student_selection_confirmed'] = true;
$_SESSION['family_student_selected_at'] = time();
unset($_SESSION['family_selection_csrf']);
session_regenerate_id(true);
header('Location: /dashboard.php');
exit;
