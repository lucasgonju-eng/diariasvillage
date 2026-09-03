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
$user = Helpers::requireAuth();
$studentId = trim((string) ($_POST['student_id'] ?? ''));
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $studentId)) {
    Helpers::json(['ok' => false, 'error' => 'Aluno inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$authUserId = trim((string) ($user['auth_user_id'] ?? ''));
if ($authUserId === '' && !empty($user['id'])) {
    $current = $client->select(
        'guardians',
        'select=auth_user_id&id=eq.' . rawurlencode((string) $user['id']) . '&limit=1'
    );
    if (($current['ok'] ?? false) && is_array($current['data'][0] ?? null)) {
        $authUserId = trim((string) ($current['data'][0]['auth_user_id'] ?? ''));
    }
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
session_regenerate_id(true);
header('Location: /dashboard.php');
exit;
