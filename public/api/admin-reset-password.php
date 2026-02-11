<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\SupabaseAuth;
use App\SupabaseClient;

Helpers::requirePost();

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Acesso negado.'], 403);
}

$payload = json_decode(file_get_contents('php://input'), true);
$cpf = trim($payload['cpf'] ?? '');
$novaSenha = $payload['nova_senha'] ?? '';

if ($cpf === '' || $novaSenha === '') {
    Helpers::json(['ok' => false, 'error' => 'Informe o CPF e a nova senha.'], 422);
}

$cpfDigits = preg_replace('/\D+/', '', $cpf) ?? '';
if (strlen($cpfDigits) !== 11) {
    Helpers::json(['ok' => false, 'error' => 'CPF inválido.'], 422);
}

if (strlen($novaSenha) < 6) {
    Helpers::json(['ok' => false, 'error' => 'A nova senha deve ter pelo menos 6 caracteres.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$guardianResult = $client->select(
    'guardians',
    'select=id,email,parent_name,parent_document,password_hash&parent_document=eq.' . urlencode($cpfDigits) . '&limit=50'
);

if (!$guardianResult['ok'] || empty($guardianResult['data'])) {
    Helpers::json(['ok' => false, 'error' => 'CPF não encontrado no cadastro.'], 404);
}

$guardians = $guardianResult['data'];
$primeiro = $guardians[0];
$email = trim($primeiro['email'] ?? '');
$usaSupabaseAuth = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && !str_contains($email, '@placeholder.');

if ($usaSupabaseAuth) {
    $auth = new SupabaseAuth(new HttpClient());
    $listResult = $auth->listUsers(1, 1000);
    $userId = null;

    if ($listResult['ok'] && !empty($listResult['data']['users'])) {
        foreach ($listResult['data']['users'] as $u) {
            if (strtolower(trim($u['email'] ?? '')) === strtolower($email)) {
                $userId = $u['id'] ?? null;
                break;
            }
        }
    }

    if ($userId) {
        $updateResult = $auth->updateUser($userId, [
            'password' => $novaSenha,
            'email_confirm' => true,
        ]);
        if (!$updateResult['ok']) {
            $upErr = $updateResult['data']['message'] ?? $updateResult['error'] ?? 'Falha ao atualizar senha.';
            Helpers::json(['ok' => false, 'error' => $upErr], 500);
        }
    }
}

$passwordHash = password_hash($novaSenha, PASSWORD_DEFAULT);
$updateGuardian = $client->update(
    'guardians',
    'parent_document=eq.' . urlencode($cpfDigits),
    ['password_hash' => $passwordHash]
);

if (!$updateGuardian['ok']) {
    $gErr = $updateGuardian['error'] ?? 'Falha ao atualizar senha local.';
    Helpers::json(['ok' => false, 'error' => $gErr], 500);
}

Helpers::json([
    'ok' => true,
    'message' => 'Senha alterada com sucesso.',
    'guardian_name' => $primeiro['parent_name'] ?? null,
]);
