<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

$isAdmin = isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
if (!$isAdmin) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

$client = new SupabaseClient(new HttpClient());
$result = $client->select('students', 'select=name&grade=in.(6,7,8)&active=eq.true&order=name.asc');

if (!$result['ok']) {
    Helpers::json(['ok' => false, 'error' => 'Erro ao buscar alunos.']);
}

$students = array_map(static fn($row) => ['name' => $row['name']], $result['data'] ?? []);
Helpers::json(['ok' => true, 'students' => $students]);
