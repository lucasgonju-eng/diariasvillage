<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Auth;
use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);

$cpf = trim($payload['cpf'] ?? '');
$password = $payload['password'] ?? '';

if ($cpf === '' || $password === '') {
    Helpers::json(['ok' => false, 'error' => 'Informe CPF e senha.'], 422);
}

$cpfDigits = preg_replace('/\D+/', '', $cpf) ?? '';
if (strlen($cpfDigits) !== 11) {
    Helpers::json(['ok' => false, 'error' => 'CPF invalido.'], 422);
}

$auth = new Auth(new SupabaseClient(new HttpClient()));
$result = $auth->login($cpfDigits, $password);

if (!$result['ok']) {
    Helpers::json(['ok' => false, 'error' => $result['error']], 401);
}

$_SESSION['user'] = $result['user'];
Helpers::json(['ok' => true]);
