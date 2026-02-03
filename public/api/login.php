<?php
require_once __DIR__ . '/../../src/Bootstrap.php';

use App\Auth;
use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);

$email = trim($payload['email'] ?? '');
$password = $payload['password'] ?? '';

if ($email === '' || $password === '') {
    Helpers::json(['ok' => false, 'error' => 'Informe e-mail e senha.'], 422);
}

$auth = new Auth(new SupabaseClient(new HttpClient()));
$result = $auth->login($email, $password);

if (!$result['ok']) {
    Helpers::json(['ok' => false, 'error' => $result['error']], 401);
}

$_SESSION['user'] = $result['user'];
Helpers::json(['ok' => true]);
