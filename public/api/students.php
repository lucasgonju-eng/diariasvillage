<?php
require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

$isAdmin = isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
if (!$isAdmin) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

$client = new SupabaseClient(new HttpClient());
$result = $client->select(
    'students',
    'select=id,name,enrollment,grade,class_name&grade=in.(6,7,8)&active=eq.true&order=name.asc'
);

if (!$result['ok']) {
    Helpers::json(['ok' => false, 'error' => 'Erro ao buscar alunos.']);
}

$students = array_map(static function (array $row): array {
    return [
        'id' => (string) ($row['id'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'enrollment' => $row['enrollment'] ?? null,
        'grade' => isset($row['grade']) ? (int) $row['grade'] : null,
        'class_name' => $row['class_name'] ?? null,
    ];
}, $result['data'] ?? []);
Helpers::json(['ok' => true, 'students' => $students]);
