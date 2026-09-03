<?php
require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Helpers;
use App\AsaasCustomerIdentity;
use App\GuardianAccountIdentity;
use App\HttpClient;
use App\SupabaseAuth;
use App\SupabaseClient;

Helpers::requirePost();
Helpers::requireAdminRole(\App\AdminAuth::ROLE_ADMIN);

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = [];
}
$action = trim((string) ($payload['action'] ?? 'reset'));
$cpf = trim($payload['cpf'] ?? '');
$novaSenha = $payload['nova_senha'] ?? '';
$guardianId = trim((string) ($payload['guardian_id'] ?? ''));

if (!in_array($action, ['lookup', 'reset'], true) || $cpf === '') {
    Helpers::json(['ok' => false, 'error' => 'Informe uma ação válida e o CPF.'], 422);
}

$cpfDigits = preg_replace('/\D+/', '', $cpf) ?? '';
if (strlen($cpfDigits) !== 11 || !AsaasCustomerIdentity::isValidCpfOrCnpj($cpfDigits)) {
    Helpers::json(['ok' => false, 'error' => 'CPF inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$guardianResult = $client->selectAll(
    'guardians',
    'select=id,student_id,email,parent_name,parent_document,password_hash,verified_at,auth_user_id,'
        . 'first_access_completed_at,students(name,enrollment)&order=id.asc'
);

if (!($guardianResult['ok'] ?? false) || !is_array($guardianResult['data'] ?? null)) {
    Helpers::json(['ok' => false, 'error' => 'Não foi possível validar a identidade do responsável.'], 503);
}

$allGuardians = array_values(array_filter($guardianResult['data'], 'is_array'));
$guardians = array_values(array_filter($allGuardians, static function (array $guardian) use ($cpfDigits): bool {
    return AsaasCustomerIdentity::normalizeDocument((string) ($guardian['parent_document'] ?? '')) === $cpfDigits;
}));
if ($guardians === []) {
    Helpers::json(['ok' => false, 'error' => 'CPF não encontrado no cadastro.'], 404);
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<string, mixed>
 */
$analyzeAccount = static function (array $rows, ?string $selectedId = null) use ($allGuardians): array {
    $identity = GuardianAccountIdentity::analyze($rows, $selectedId);
    if (($identity['ok'] ?? false) && ($identity['mode'] ?? '') === 'supabase_auth') {
        $authUserId = trim((string) ($identity['auth_user_id'] ?? ''));
        $accountRows = array_values(array_filter(
            $allGuardians,
            static fn (array $guardian): bool =>
                trim((string) ($guardian['auth_user_id'] ?? '')) === $authUserId
        ));
        $identity = GuardianAccountIdentity::analyze($accountRows, $selectedId);
        if ($identity['ok'] ?? false) {
            $identity['guardians'] = $accountRows;
        }
    }
    return $identity;
};

$identity = $analyzeAccount($guardians, $action === 'reset' ? $guardianId : null);
$candidates = array_map(static function (array $guardian): array {
    $student = is_array($guardian['students'] ?? null) ? $guardian['students'] : [];
    $email = strtolower(trim((string) ($guardian['email'] ?? '')));
    [$emailUser, $emailDomain] = str_contains($email, '@') ? explode('@', $email, 2) : ['', ''];
    $maskedEmail = $emailUser !== '' && $emailDomain !== ''
        ? substr($emailUser, 0, 1) . '***@' . $emailDomain
        : 'sem e-mail válido';
    return [
        'guardian_id' => trim((string) ($guardian['id'] ?? '')),
        'guardian_name' => trim((string) ($guardian['parent_name'] ?? '')),
        'student_name' => trim((string) ($student['name'] ?? '')),
        'enrollment' => trim((string) ($student['enrollment'] ?? '')),
        'email_masked' => $maskedEmail,
        'auth_linked' => trim((string) ($guardian['auth_user_id'] ?? '')) !== '',
    ];
}, $guardians);

if ($action === 'lookup') {
    Helpers::json([
        'ok' => true,
        'blocked' => !($identity['ok'] ?? false),
        'code' => (string) ($identity['code'] ?? ''),
        'mode' => (string) ($identity['mode'] ?? ''),
        'candidates' => $candidates,
    ]);
}

if ($guardianId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $guardianId)) {
    Helpers::json(['ok' => false, 'error' => 'Selecione explicitamente o vínculo do responsável.'], 422);
}
if (!($identity['ok'] ?? false)) {
    Helpers::json([
        'ok' => false,
        'code' => (string) ($identity['code'] ?? 'GUARDIAN_ACCOUNT_CONFLICT'),
        'error' => 'CPF com identidade ou conta conflitante. Revise os vínculos antes de redefinir a senha.',
        'candidates' => $candidates,
    ], 409);
}
if (!is_string($novaSenha) || strlen($novaSenha) < 6) {
    Helpers::json(['ok' => false, 'error' => 'A nova senha deve ter pelo menos 6 caracteres.'], 422);
}

$targetRows = is_array($identity['guardians'] ?? null) ? $identity['guardians'] : [];
$adminId = trim((string) ($_SESSION['admin_id'] ?? ''));
$auditStart = $client->insert('admin_audit_log', [[
    'admin_user_id' => $adminId !== '' ? $adminId : null,
    'username' => (string) ($_SESSION['admin_user'] ?? 'admin'),
    'role' => (string) ($_SESSION['admin_role'] ?? \App\AdminAuth::ROLE_ADMIN),
    'action' => 'GUARDIAN_PASSWORD_RESET',
    'entity_type' => 'guardian_account',
    'entity_id' => ($identity['mode'] ?? '') === 'supabase_auth'
        ? (string) ($identity['auth_user_id'] ?? '')
        : $guardianId,
    'success' => false,
    'details' => [
        'state' => 'STARTED',
        'mode' => (string) ($identity['mode'] ?? ''),
        'guardian_count' => count($targetRows),
    ],
]]);
if (!($auditStart['ok'] ?? false) || empty($auditStart['data'][0]['id'])) {
    Helpers::json([
        'ok' => false,
        'error' => 'Não foi possível registrar a operação. Nenhuma senha foi alterada.',
    ], 503);
}
$auditId = (string) $auditStart['data'][0]['id'];
$updateAudit = static function (bool $success, string $state) use (
    $client,
    $auditId,
    $identity,
    $targetRows
): bool {
    $result = $client->update(
        'admin_audit_log',
        'id=eq.' . rawurlencode($auditId),
        [
            'success' => $success,
            'details' => [
                'state' => $state,
                'mode' => (string) ($identity['mode'] ?? ''),
                'guardian_count' => count($targetRows),
            ],
        ]
    );
    return ($result['ok'] ?? false) && count($result['data'] ?? []) === 1;
};

if (($identity['mode'] ?? '') === 'supabase_auth') {
    $authUserId = trim((string) ($identity['auth_user_id'] ?? ''));

    // O hash local não autentica contas Auth. Troque-o por valor desconhecido
    // antes da chamada externa para impedir qualquer fallback antigo.
    $disabledLocalHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $localUpdate = $client->update(
        'guardians',
        'auth_user_id=eq.' . rawurlencode($authUserId),
        ['password_hash' => $disabledLocalHash]
    );
    if (!($localUpdate['ok'] ?? false) || count($localUpdate['data'] ?? []) !== count($targetRows)) {
        $updateAudit(false, 'LOCAL_FALLBACK_DISABLE_FAILED');
        Helpers::json([
            'ok' => false,
            'error' => 'Os vínculos mudaram durante a operação. A senha Auth não foi alterada.',
        ], 409);
    }

    $auth = new SupabaseAuth(new HttpClient());
    $updateResult = $auth->updateUser($authUserId, [
        'password' => $novaSenha,
        'email_confirm' => true,
    ]);
    if (!($updateResult['ok'] ?? false)) {
        $updateAudit(false, 'AUTH_UPDATE_FAILED');
        Helpers::json([
            'ok' => false,
            'error' => 'Não foi possível atualizar a conta vinculada no Supabase Auth.',
        ], 502);
    }
} else {
    $passwordHash = password_hash($novaSenha, PASSWORD_DEFAULT);
    $localUpdate = $client->update(
        'guardians',
        'id=eq.' . rawurlencode($guardianId) . '&auth_user_id=is.null',
        ['password_hash' => $passwordHash]
    );
    if (!($localUpdate['ok'] ?? false) || count($localUpdate['data'] ?? []) !== 1) {
        $updateAudit(false, 'LEGACY_UPDATE_FAILED');
        Helpers::json([
            'ok' => false,
            'error' => 'O vínculo mudou durante a operação. A senha não foi alterada.',
        ], 409);
    }
}

$auditCompleted = $updateAudit(true, 'COMPLETED');
if (!$auditCompleted) {
    error_log('[admin-reset-password] operação concluída com registro STARTED preservado');
}

$selected = is_array($identity['selected'] ?? null) ? $identity['selected'] : ($targetRows[0] ?? []);
Helpers::json([
    'ok' => true,
    'message' => 'Senha alterada para a conta explicitamente selecionada.',
    'guardian_name' => $selected['parent_name'] ?? null,
    'updated_guardians' => count($targetRows),
    'audit_complete' => $auditCompleted,
]);
