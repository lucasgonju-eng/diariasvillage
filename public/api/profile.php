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

use App\AsaasCustomerIdentity;
use App\GuardianAccountIdentity;
use App\GuardianSession;
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
    $csrfToken = trim((string) ($payload['csrf_token'] ?? ''));
    $expectedCsrfToken = trim((string) ($_SESSION['profile_csrf_token'] ?? ''));
    if (
        $csrfToken === ''
        || $expectedCsrfToken === ''
        || !hash_equals($expectedCsrfToken, $csrfToken)
    ) {
        Helpers::json(['ok' => false, 'error' => 'Sessão expirada. Recarregue o perfil.'], 403);
    }

    $guardianId = trim((string) ($user['id'] ?? ''));
    if ($guardianId === '') {
        Helpers::json(['ok' => false, 'error' => 'Sessão inválida. Faça login novamente.'], 401);
    }

    $requestedName = trim((string) ($payload['parent_name'] ?? ''));
    $requestedPhone = trim((string) ($payload['parent_phone'] ?? ''));
    $document = AsaasCustomerIdentity::normalizeDocument((string) ($payload['parent_document'] ?? ''));
    if ($requestedName === '' || !AsaasCustomerIdentity::isValidCpfOrCnpj($document)) {
        Helpers::json(['ok' => false, 'error' => 'Nome ou CPF/CNPJ inválido.'], 422);
    }

    $password = (string) ($payload['password'] ?? '');
    $passwordConfirm = (string) ($payload['password_confirm'] ?? '');

    if ($password !== '') {
        if ($password !== $passwordConfirm) {
            Helpers::json(['ok' => false, 'error' => 'As senhas não conferem.'], 422);
        }
        if (strlen($password) < 6) {
            Helpers::json(['ok' => false, 'error' => 'A nova senha deve ter pelo menos 6 caracteres.'], 422);
        }
    }

    $client = new SupabaseClient(new HttpClient());
    $currentResult = $client->select(
        'guardians',
        'select=*&id=eq.' . rawurlencode($guardianId) . '&limit=1'
    );
    $current = (($currentResult['ok'] ?? false) && is_array($currentResult['data'][0] ?? null))
        ? $currentResult['data'][0]
        : null;
    if (!is_array($current)) {
        Helpers::json(['ok' => false, 'error' => 'Responsável da sessão não encontrado.'], 409);
    }

    $normalizeName = static function (string $name): string {
        $normalized = mb_strtoupper(trim($name), 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if ($ascii !== false) {
            $normalized = $ascii;
        }
        return preg_replace('/[^A-Z0-9]+/', '', $normalized) ?? '';
    };
    if (
        $normalizeName($requestedName) !== $normalizeName((string) ($current['parent_name'] ?? ''))
        || $document !== AsaasCustomerIdentity::normalizeDocument((string) ($current['parent_document'] ?? ''))
    ) {
        Helpers::json([
            'ok' => false,
            'error' => 'Nome e CPF/CNPJ identificam a conta e só podem ser alterados pela secretaria.',
        ], 409);
    }

    $authUserId = trim((string) ($current['auth_user_id'] ?? ''));
    $targetRows = [$current];
    $updateFilter = 'id=eq.' . rawurlencode($guardianId) . '&auth_user_id=is.null';
    if ($authUserId !== '') {
        $accountResult = $client->selectAll(
            'guardians',
            'select=*&auth_user_id=eq.' . rawurlencode($authUserId) . '&order=id.asc'
        );
        $targetRows = (($accountResult['ok'] ?? false) && is_array($accountResult['data'] ?? null))
            ? array_values(array_filter($accountResult['data'], 'is_array'))
            : [];
        $identity = GuardianAccountIdentity::analyze($targetRows, $guardianId);
        if (
            !($identity['ok'] ?? false)
            || ($identity['mode'] ?? '') !== 'supabase_auth'
            || !hash_equals($authUserId, (string) ($identity['auth_user_id'] ?? ''))
        ) {
            Helpers::json(['ok' => false, 'error' => 'A conta familiar possui vínculos conflitantes.'], 409);
        }
        $updateFilter = 'auth_user_id=eq.' . rawurlencode($authUserId);
    }
    $currentSessionVersion = (int) ($current['account_session_version'] ?? 0);
    if ($currentSessionVersion < 1) {
        Helpers::json(['ok' => false, 'error' => 'A versão da conta é inválida. Entre novamente.'], 409);
    }
    foreach ($targetRows as $targetRow) {
        if ((int) ($targetRow['account_session_version'] ?? 0) !== $currentSessionVersion) {
            Helpers::json(['ok' => false, 'error' => 'A conta familiar possui sessões conflitantes.'], 409);
        }
    }

    $update = [
        'parent_name' => (string) ($current['parent_name'] ?? $requestedName),
        'parent_phone' => $requestedPhone,
        'parent_document' => $document,
    ];
    if ($password !== '') {
        $update['password_hash'] = $authUserId !== ''
            ? password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)
            : password_hash($password, PASSWORD_DEFAULT);
        if ($authUserId === '') {
            $update['account_session_version'] = $currentSessionVersion + 1;
            $updateFilter .= '&account_session_version=eq.' . $currentSessionVersion;
        }
    }

    $result = $client->update('guardians', $updateFilter, $update);
    if (!($result['ok'] ?? false) || count($result['data'] ?? []) !== count($targetRows)) {
        Helpers::json(['ok' => false, 'error' => 'Os vínculos mudaram durante a atualização. Tente novamente.'], 409);
    }

    if ($password !== '' && $authUserId !== '') {
        $rotation = (new GuardianSession($client))->rotate(
            $guardianId,
            $authUserId,
            $currentSessionVersion
        );
        if (
            !($rotation['ok'] ?? false)
            || (int) ($rotation['session_version'] ?? 0) !== $currentSessionVersion + 1
            || (int) ($rotation['updated_guardians'] ?? 0) !== count($targetRows)
        ) {
            Helpers::json([
                'ok' => false,
                'error' => 'Os vínculos mudaram. A senha não foi alterada.',
            ], 409);
        }
        $auth = new SupabaseAuth(new HttpClient());
        $authUpdate = $auth->updateUser($authUserId, [
            'password' => $password,
            'email_confirm' => true,
        ]);
        if (!($authUpdate['ok'] ?? false)) {
            Helpers::clearUserSession();
            Helpers::json([
                'ok' => false,
                'error' => 'A senha Auth não pôde ser alterada. Entre novamente com a senha anterior.',
            ], 502);
        }
    }

    $updatedUser = null;
    foreach (($result['data'] ?? []) as $updatedRow) {
        if (is_array($updatedRow) && (string) ($updatedRow['id'] ?? '') === $guardianId) {
            $updatedUser = $updatedRow;
            break;
        }
    }
    if (!is_array($updatedUser)) {
        Helpers::json(['ok' => false, 'error' => 'Perfil salvo, mas a sessão precisa ser refeita.'], 409);
    }
    if ($password !== '') {
        $updatedUser['account_session_version'] = $currentSessionVersion + 1;
    }
    Helpers::establishUserSession($updatedUser, false);
    Helpers::json(['ok' => true]);
} catch (Throwable $e) {
    error_log('profile.php error: ' . $e->getMessage());
    Helpers::json(['ok' => false, 'error' => 'Erro inesperado ao atualizar perfil.'], 500);
}
