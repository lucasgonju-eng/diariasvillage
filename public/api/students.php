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
    $result = $client->select(
        'students',
        'select=id,name,enrollment,grade,class_name&grade=in.(6,7,8)&active=eq.true&order=name.asc'
    );

    if (!($result['ok'] ?? false)) {
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
            'class_name' => $row['class_name'] ?? null,
        ];
    }

    Helpers::json(['ok' => true, 'students' => $students]);
} catch (\Throwable $e) {
    error_log('students.php fatal: ' . $e->getMessage());
    Helpers::json(['ok' => false, 'error' => 'Falha interna ao carregar alunos.'], 500);
}
