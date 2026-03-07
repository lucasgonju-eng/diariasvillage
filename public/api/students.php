<?php
require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

$isAdmin = isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
if (!$isAdmin) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

try {
    $client = new SupabaseClient(new HttpClient());
    $queries = [
        'select=id,name,enrollment,grade,class_name&grade=in.(6,7,8)&active=eq.true&order=name.asc&limit=10000',
        'select=id,name,enrollment,grade,class_name&active=eq.true&order=name.asc&limit=10000',
        'select=id,name,enrollment,grade,class_name&order=name.asc&limit=10000',
        'select=id,name,enrollment,active&active=eq.true&order=name.asc&limit=10000',
        'select=id,name,enrollment&order=name.asc&limit=10000',
    ];

    $result = null;
    foreach ($queries as $query) {
        $attempt = $client->select('students', $query);
        if ($attempt['ok'] ?? false) {
            $result = $attempt;
            break;
        }
    }

    if (!is_array($result) || !($result['ok'] ?? false)) {
        Helpers::json(['ok' => false, 'error' => 'Erro ao buscar alunos.'], 500);
    }

    $rows = $result['data'] ?? [];
    if (!is_array($rows)) {
        $rows = [];
    }

    $students = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $students[] = [
            'id' => (string) ($row['id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'enrollment' => $row['enrollment'] ?? null,
            'grade' => isset($row['grade']) ? (int) $row['grade'] : null,
            'class_name' => $row['class_name'] ?? ($row['class'] ?? null),
        ];
    }

    Helpers::json(['ok' => true, 'students' => $students]);
} catch (\Throwable $e) {
    error_log('students.php fatal: ' . $e->getMessage());
    Helpers::json(['ok' => false, 'error' => 'Falha interna ao carregar alunos.'], 500);
}
