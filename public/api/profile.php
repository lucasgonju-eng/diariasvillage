<?php
require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\SupabaseAuth;
use App\SupabaseClient;

try {
    Helpers::requirePost();
    $user = Helpers::requireAuth();
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        Helpers::json(['ok' => false, 'error' => 'Payload inválido.'], 422);
    }

    $guardianId = trim((string) ($user['id'] ?? ''));
    if ($guardianId === '') {
        Helpers::json(['ok' => false, 'error' => 'Sessão inválida. Faça login novamente.'], 401);
    }

    $documentRaw = trim((string) ($payload['parent_document'] ?? ''));
    $document = $documentRaw !== '' ? preg_replace('/\D+/', '', $documentRaw) : '';

    $update = [
        'parent_name' => trim((string) ($payload['parent_name'] ?? '')),
        'parent_phone' => trim((string) ($payload['parent_phone'] ?? '')),
        'parent_document' => $document,
    ];

    $password = (string) ($payload['password'] ?? '');
    $passwordConfirm = (string) ($payload['password_confirm'] ?? '');

    if ($password !== '') {
        if ($password !== $passwordConfirm) {
            Helpers::json(['ok' => false, 'error' => 'As senhas não conferem.'], 422);
        }
        if (strlen($password) < 6) {
            Helpers::json(['ok' => false, 'error' => 'A nova senha deve ter pelo menos 6 caracteres.'], 422);
        }
        $update['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    $client = new SupabaseClient(new HttpClient());
    $result = $client->update('guardians', 'id=eq.' . urlencode($guardianId), $update);

    if (!$result['ok'] || empty($result['data'][0])) {
        $data = $result['data'] ?? [];
        $error = is_array($data)
            ? ($data['message'] ?? $data['details'] ?? $data['error_description'] ?? null)
            : null;
        Helpers::json(['ok' => false, 'error' => $error ?: 'Erro ao atualizar perfil.'], 500);
    }

    $updatedUser = $result['data'][0];

    if ($password !== '') {
        $email = trim((string) ($updatedUser['email'] ?? $user['email'] ?? ''));
        $canUseAuth = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && !str_contains(strtolower($email), '@placeholder.');
        if ($canUseAuth) {
            $auth = new SupabaseAuth(new HttpClient());
            $listResult = $auth->listUsers(1, 1000);
            $authUserId = null;

            if ($listResult['ok'] && !empty($listResult['data']['users'])) {
                foreach ($listResult['data']['users'] as $authUser) {
                    if (strtolower(trim((string) ($authUser['email'] ?? ''))) === strtolower($email)) {
                        $authUserId = $authUser['id'] ?? null;
                        break;
                    }
                }
            }

            if ($authUserId) {
                $authUpdate = $auth->updateUser((string) $authUserId, [
                    'password' => $password,
                    'email_confirm' => true,
                ]);
                if (!$authUpdate['ok']) {
                    $authData = $authUpdate['data'] ?? [];
                    $authError = is_array($authData)
                        ? ($authData['msg'] ?? $authData['message'] ?? $authData['error_description'] ?? null)
                        : null;
                    Helpers::json(['ok' => false, 'error' => $authError ?: 'Perfil salvo, mas falhou ao atualizar a senha de autenticação.'], 500);
                }
            }
        }
    }

    $_SESSION['user'] = $updatedUser;
    Helpers::json(['ok' => true]);
} catch (Throwable $e) {
    error_log('profile.php error: ' . $e->getMessage());
    Helpers::json(['ok' => false, 'error' => 'Erro inesperado ao atualizar perfil.'], 500);
}
