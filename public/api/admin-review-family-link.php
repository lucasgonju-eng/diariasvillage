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

$passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
$client = new SupabaseClient(new HttpClient());
$review = $client->rpc('review_family_link_request', [
    'p_request_id' => $requestId,
    'p_admin_user_id' => (string) ($admin['id'] ?? ''),
    'p_decision' => $decision,
    'p_note' => $note,
    'p_password_hash' => $passwordHash,
]);
$data = is_array($review['data'] ?? null) ? $review['data'] : [];
if (!($review['ok'] ?? false) || !($data['ok'] ?? false)) {
    $code = (string) ($data['code'] ?? 'FAMILY_LINK_REVIEW_FAILED');
    $conflictMessages = [
        'REQUESTER_ACCOUNT_LINK_CHANGED' => 'A conta do responsável mudou desde a solicitação.',
        'REQUESTER_IDENTITY_INCOMPLETE' => 'A identidade do responsável está incompleta.',
        'REQUESTER_ACCOUNT_IDENTITY_CONFLICT' => 'A conta possui nome, e-mail ou documento divergente.',
        'REQUESTER_ASAAS_LINK_CONFLICT' => 'A conta possui mais de um cliente Asaas e exige revisão financeira.',
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
