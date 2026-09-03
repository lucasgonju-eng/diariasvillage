<?php
declare(strict_types=1);

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

Helpers::requirePost();

$auth = new AdminAuth();
$admin = Helpers::requireAdminRole(AdminAuth::ROLE_ADMIN, $auth);
$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    Helpers::json(['ok' => false, 'error' => 'Dados inválidos.'], 422);
}

$password = (string) ($payload['password'] ?? '');
$confirmation = (string) ($payload['password_confirmation'] ?? '');
if ($password === '' || !hash_equals($password, $confirmation)) {
    Helpers::json(['ok' => false, 'error' => 'As senhas não conferem.'], 422);
}

$result = $auth->configureSecretariaPassword($admin, $password);
if (!($result['ok'] ?? false)) {
    $status = ($result['error'] ?? '') === 'Acesso negado.' ? 403 : 422;
    Helpers::json(['ok' => false, 'error' => $result['error'] ?? 'Falha ao salvar acesso.'], $status);
}

Helpers::json([
    'ok' => true,
    'message' => ($result['created'] ?? false)
        ? 'Acesso da secretaria criado. A senha legada deixou de funcionar.'
        : 'Senha da secretaria alterada. Sessões anteriores foram encerradas.',
]);
