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

use App\AdminAuth;
use App\AsaasClient;
use App\AsaasCustomerIdentity;
use App\GuardianAccountIdentity;
use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

Helpers::requirePost();
$admin = Helpers::requireAdminRole([AdminAuth::ROLE_ADMIN, AdminAuth::ROLE_SECRETARIA]);
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = [];
}

$requestId = trim((string) ($payload['request_id'] ?? ''));
$decision = strtoupper(trim((string) ($payload['decision'] ?? '')));
$note = trim((string) ($payload['note'] ?? ''));
$csrfToken = trim((string) ($payload['csrf_token'] ?? ''));
$expectedCsrfToken = trim((string) ($_SESSION['admin_csrf_token'] ?? ''));
if (
    $csrfToken === ''
    || $expectedCsrfToken === ''
    || !hash_equals($expectedCsrfToken, $csrfToken)
) {
    Helpers::json(['ok' => false, 'error' => 'Sessão administrativa expirada. Recarregue o painel.'], 403);
}
if (
    !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $requestId)
    || !in_array($decision, ['APPROVE', 'REJECT'], true)
) {
    Helpers::json(['ok' => false, 'error' => 'Solicitação ou decisão inválida.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$validatedAsaasCustomerId = '';
$validatedIdentityFingerprint = '';
if ($decision === 'APPROVE') {
    $requestResult = $client->select(
        'family_link_requests',
        'select=id,requester_auth_user_id,requester_guardian_id,status'
            . '&id=eq.' . rawurlencode($requestId)
            . '&limit=1'
    );
    $request = (($requestResult['ok'] ?? false) && is_array($requestResult['data'][0] ?? null))
        ? $requestResult['data'][0]
        : null;
    $requesterAuthUserId = trim((string) ($request['requester_auth_user_id'] ?? ''));
    $requesterGuardianId = trim((string) ($request['requester_guardian_id'] ?? ''));
    if (
        !is_array($request)
        || strtoupper((string) ($request['status'] ?? '')) !== 'PENDING'
        || $requesterAuthUserId === ''
        || $requesterGuardianId === ''
    ) {
        Helpers::json([
            'ok' => false,
            'code' => 'FAMILY_LINK_REVIEW_PRECHECK_FAILED',
            'error' => 'A solicitação mudou ou não está mais disponível para aprovação.',
        ], 409);
    }

    $accountResult = $client->selectAll(
        'guardians',
        'select=*&auth_user_id=eq.' . rawurlencode($requesterAuthUserId) . '&order=id.asc'
    );
    $accountRows = (($accountResult['ok'] ?? false) && is_array($accountResult['data'] ?? null))
        ? array_values(array_filter($accountResult['data'], 'is_array'))
        : [];
    $identity = GuardianAccountIdentity::analyze($accountRows, $requesterGuardianId);
    if (
        !($identity['ok'] ?? false)
        || ($identity['mode'] ?? '') !== 'supabase_auth'
        || !hash_equals($requesterAuthUserId, (string) ($identity['auth_user_id'] ?? ''))
        || !is_array($identity['selected'] ?? null)
    ) {
        Helpers::json([
            'ok' => false,
            'code' => 'REQUESTER_ACCOUNT_IDENTITY_CONFLICT',
            'error' => 'A conta solicitante precisa de revisão de identidade.',
        ], 409);
    }

    $customerIds = [];
    foreach ($identity['guardians'] as $guardian) {
        $customerId = trim((string) ($guardian['asaas_customer_id'] ?? ''));
        if ($customerId !== '') {
            $customerIds[$customerId] = true;
        }
    }
    if (count($customerIds) > 1) {
        Helpers::json([
            'ok' => false,
            'code' => 'REQUESTER_ASAAS_LINK_CONFLICT',
            'error' => 'A conta possui mais de um cliente Asaas e exige revisão financeira.',
        ], 409);
    }
    $validatedIdentityFingerprint = AsaasCustomerIdentity::identityFingerprint($identity['selected']);
    if ($customerIds !== []) {
        $validatedAsaasCustomerId = (string) array_key_first($customerIds);
        $asaas = new AsaasClient(new HttpClient());
        $validation = (new AsaasCustomerIdentity($asaas, $client))->validateExisting(
            $identity['selected'],
            $validatedAsaasCustomerId
        );
        if (!($validation['ok'] ?? false)) {
            Helpers::json([
                'ok' => false,
                'code' => (string) ($validation['code'] ?? 'ASAAS_CUSTOMER_VALIDATION_FAILED'),
                'error' => (string) ($validation['error'] ?? 'Não foi possível validar o cliente Asaas.'),
            ], (int) ($validation['status'] ?? 409));
        }
        if (!hash_equals(
            $validatedIdentityFingerprint,
            (string) ($validation['identity_fingerprint'] ?? '')
        )) {
            Helpers::json([
                'ok' => false,
                'code' => 'REQUESTER_IDENTITY_CHANGED_AFTER_VALIDATION',
                'error' => 'A identidade local mudou durante a validação. Tente novamente.',
            ], 409);
        }
    }
}

$passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
$review = $client->rpc('review_family_link_request', [
    'p_request_id' => $requestId,
    'p_admin_user_id' => (string) ($admin['id'] ?? ''),
    'p_decision' => $decision,
    'p_note' => $note,
    'p_password_hash' => $passwordHash,
    'p_validated_asaas_customer_id' => $validatedAsaasCustomerId,
    'p_validated_identity_fingerprint' => $validatedIdentityFingerprint,
]);
$data = is_array($review['data'] ?? null) ? $review['data'] : [];
if (!($review['ok'] ?? false) || !($data['ok'] ?? false)) {
    $code = (string) ($data['code'] ?? 'FAMILY_LINK_REVIEW_FAILED');
    $conflictMessages = [
        'REQUESTER_ACCOUNT_LINK_CHANGED' => 'A conta do responsável mudou desde a solicitação.',
        'REQUESTER_IDENTITY_INCOMPLETE' => 'A identidade do responsável está incompleta.',
        'REQUESTER_ACCOUNT_IDENTITY_CONFLICT' => 'A conta possui nome, e-mail ou documento divergente.',
        'REQUESTER_ASAAS_LINK_CONFLICT' => 'A conta possui mais de um cliente Asaas e exige revisão financeira.',
        'REQUESTER_ASAAS_VALIDATION_REQUIRED' => 'O cliente Asaas da conta mudou e precisa ser validado novamente.',
        'REQUESTER_IDENTITY_CHANGED_AFTER_VALIDATION' => 'A identidade mudou durante a validação. Tente novamente.',
        'TARGET_ENROLLMENT_NOT_UNIQUE' => 'A matrícula não existe ou não identifica um único aluno.',
        'TARGET_STUDENT_NOT_ACTIVE' => 'O aluno solicitado não está ativo.',
        'TARGET_STUDENT_ALREADY_SELECTED' => 'A matrícula informada já é a do aluno atual.',
        'TARGET_ACCOUNT_DOCUMENT_CONFLICT' => 'Já existe vínculo desta conta com documento divergente.',
        'TARGET_GUARDIAN_AMBIGUOUS' => 'Há mais de um responsável candidato no aluno solicitado.',
        'TARGET_GUARDIAN_NAME_CONFLICT' => 'O CPF aparece com outro nome no aluno solicitado.',
        'TARGET_GUARDIAN_EMAIL_CONFLICT' => 'O CPF aparece com outro e-mail no aluno solicitado.',
        'TARGET_GUARDIAN_AUTH_CONFLICT' => 'O responsável do aluno já pertence a outra conta Auth.',
        'TARGET_GUARDIAN_ASAAS_CONFLICT' => 'O responsável do aluno possui outro cliente Asaas.',
        'FAMILY_LINK_REQUEST_ALREADY_REVIEWED' => 'A solicitação já foi analisada.',
    ];
    Helpers::json([
        'ok' => false,
        'code' => $code,
        'error' => $conflictMessages[$code] ?? 'Não foi possível concluir a revisão com segurança.',
    ], 409);
}

Helpers::json([
    'ok' => true,
    'status' => (string) ($data['status'] ?? ''),
    'message' => $decision === 'APPROVE'
        ? 'Vínculo familiar aprovado. O filho aparecerá no próximo acesso.'
        : 'Solicitação rejeitada sem ampliar o acesso.',
]);
