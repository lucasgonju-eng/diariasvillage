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

use App\Auth;
use App\AsaasCustomerIdentity;
use App\GuardianAccountIdentity;
use App\Helpers;
use App\HttpClient;
use App\LoginThrottle;
use App\SupabaseClient;

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = [];
}

$cpf = trim($payload['cpf'] ?? '');
$password = $payload['password'] ?? '';

if ($cpf === '' || $password === '') {
    Helpers::json(['ok' => false, 'error' => 'Informe CPF e senha.'], 422);
}

$cpfDigits = preg_replace('/\D+/', '', $cpf) ?? '';
$client = new SupabaseClient(new HttpClient());
$throttle = new LoginThrottle($client);
$throttleClaim = $throttle->claim('guardian', $cpfDigits !== '' ? $cpfDigits : $cpf);
if (!($throttleClaim['ok'] ?? false)) {
    Helpers::json(['ok' => false, 'error' => 'Login temporariamente indisponível. Tente novamente.'], 503);
}
if (!($throttleClaim['allowed'] ?? false)) {
    $retryAfter = max(1, (int) ($throttleClaim['retry_after'] ?? 60));
    header('Retry-After: ' . $retryAfter);
    Helpers::json([
        'ok' => false,
        'error' => 'Muitas tentativas. Aguarde alguns minutos antes de tentar novamente.',
    ], 429);
}

if (strlen($cpfDigits) !== 11 || !AsaasCustomerIdentity::isValidCpfOrCnpj($cpfDigits)) {
    Helpers::json(['ok' => false, 'error' => 'CPF inválido.'], 422);
}

$auth = new Auth($client);
$result = $auth->login($cpfDigits, $password);

if (!$result['ok']) {
    Helpers::json(['ok' => false, 'error' => $result['error']], 401);
}
if (!$throttle->clearAfterSuccess()) {
    error_log('[login] não foi possível liberar a reserva após autenticação válida');
}

$user = is_array($result['user'] ?? null) ? $result['user'] : [];
$authUserId = trim((string) ($user['auth_user_id'] ?? ''));
$familyGuardians = [$user];
if ($authUserId !== '') {
    $familyResult = $client->selectAll(
        'guardians',
        'select=*&auth_user_id=eq.' . rawurlencode($authUserId) . '&order=id.asc'
    );
    if (!($familyResult['ok'] ?? false) || !is_array($familyResult['data'] ?? null)) {
        Helpers::json([
            'ok' => false,
            'error' => 'Não foi possível carregar os filhos desta conta. Tente novamente.',
        ], 503);
    }
    $familyGuardians = array_values(array_filter($familyResult['data'], 'is_array'));
    $familyIdentity = GuardianAccountIdentity::analyze($familyGuardians);
    if (
        !($familyIdentity['ok'] ?? false)
        || ($familyIdentity['mode'] ?? '') !== 'supabase_auth'
        || !hash_equals($authUserId, (string) ($familyIdentity['auth_user_id'] ?? ''))
    ) {
        Helpers::json([
            'ok' => false,
            'error' => 'Os vínculos desta conta precisam de revisão pela secretaria.',
        ], 409);
    }
}

$studentIds = [];
foreach ($familyGuardians as $guardian) {
    $studentId = trim((string) ($guardian['student_id'] ?? ''));
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $studentId)) {
        Helpers::json([
            'ok' => false,
            'error' => 'A conta familiar possui um vínculo incompleto. Procure a secretaria.',
        ], 409);
    }
    $studentIds[$studentId] = true;
}
if ($studentIds === []) {
    Helpers::json([
        'ok' => false,
        'error' => 'Nenhum aluno foi encontrado para esta conta.',
    ], 409);
}

$requiresSelection = count($studentIds) > 1;
Helpers::establishUserSession($user);
$_SESSION['family_student_selection_required'] = $requiresSelection;
$_SESSION['family_student_selection_confirmed'] = !$requiresSelection;
$_SESSION['family_student_count'] = count($studentIds);
if ($requiresSelection) {
    unset($_SESSION['family_student_selected_at']);
} else {
    $_SESSION['family_student_selected_at'] = time();
}

Helpers::json([
    'ok' => true,
    'requires_student_selection' => $requiresSelection,
    'redirect' => $requiresSelection ? '/selecionar-aluno.php' : '/dashboard.php',
]);
