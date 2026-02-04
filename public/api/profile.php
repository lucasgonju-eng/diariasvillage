<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

Helpers::requirePost();
$user = Helpers::requireAuth();
$payload = json_decode(file_get_contents('php://input'), true);

$documentRaw = trim($payload['parent_document'] ?? '');
$document = $documentRaw !== '' ? preg_replace('/\D+/', '', $documentRaw) : '';

$update = [
    'parent_name' => trim($payload['parent_name'] ?? ''),
    'parent_phone' => trim($payload['parent_phone'] ?? ''),
    'parent_document' => $document,
];

$password = $payload['password'] ?? '';
$passwordConfirm = $payload['password_confirm'] ?? '';

if ($password !== '') {
    if ($password !== $passwordConfirm) {
        Helpers::json(['ok' => false, 'error' => 'As senhas nao conferem.'], 422);
    }
    $update['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
}

$client = new SupabaseClient(new HttpClient());
$result = $client->update('guardians', 'id=eq.' . $user['id'], $update);

if (!$result['ok']) {
    Helpers::json(['ok' => false, 'error' => 'Erro ao atualizar perfil.'], 500);
}

$_SESSION['user'] = $result['data'][0];
Helpers::json(['ok' => true]);
