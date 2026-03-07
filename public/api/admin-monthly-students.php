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

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

function monthly_storage_path(): string
{
    $root = dirname(__DIR__, 2);
    $preferred = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'monthly_students.json';
    $legacy = $root . DIRECTORY_SEPARATOR . 'monthly_students.json';
    if (is_file($preferred)) {
        return $preferred;
    }
    if (is_file($legacy)) {
        return $legacy;
    }
    return $preferred;
}

function monthly_load_items(): array
{
    $path = monthly_storage_path();
    if (!is_file($path)) {
        return [];
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $items = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }
        $studentId = trim((string) ($row['student_id'] ?? ''));
        $weeklyDays = (int) ($row['weekly_days'] ?? 0);
        if ($studentId === '' || !in_array($weeklyDays, [2, 3, 4, 5], true)) {
            continue;
        }
        $items[] = [
            'student_id' => $studentId,
            'student_name' => trim((string) ($row['student_name'] ?? '')),
            'enrollment' => trim((string) ($row['enrollment'] ?? '')),
            'weekly_days' => $weeklyDays,
            'active' => ($row['active'] ?? true) !== false,
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'updated_by' => trim((string) ($row['updated_by'] ?? '')),
        ];
    }
    return $items;
}

function monthly_save_items(array $items): bool
{
    $normalized = [];
    foreach ($items as $row) {
        if (!is_array($row)) {
            continue;
        }
        $studentId = trim((string) ($row['student_id'] ?? ''));
        $weeklyDays = (int) ($row['weekly_days'] ?? 0);
        if ($studentId === '' || !in_array($weeklyDays, [2, 3, 4, 5], true)) {
            continue;
        }
        $normalized[] = [
            'student_id' => $studentId,
            'student_name' => trim((string) ($row['student_name'] ?? '')),
            'enrollment' => trim((string) ($row['enrollment'] ?? '')),
            'weekly_days' => $weeklyDays,
            'active' => ($row['active'] ?? true) !== false,
            'updated_at' => (string) ($row['updated_at'] ?? date('c')),
            'updated_by' => trim((string) ($row['updated_by'] ?? '')),
        ];
    }

    usort($normalized, static function (array $a, array $b): int {
        return strcmp((string) ($a['student_name'] ?? ''), (string) ($b['student_name'] ?? ''));
    });

    $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return false;
    }

    $path = monthly_storage_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
    }
    return @file_put_contents($path, $json . PHP_EOL, LOCK_EX) !== false;
}

try {
    if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
        Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        Helpers::json(['ok' => true, 'items' => monthly_load_items()]);
    }

    Helpers::requirePost();
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = [];
    }

    $action = strtolower(trim((string) ($payload['action'] ?? 'set')));
    $studentId = trim((string) ($payload['student_id'] ?? ''));

    if ($studentId === '') {
        Helpers::json(['ok' => false, 'error' => 'Aluno inválido.'], 422);
    }

    $items = monthly_load_items();

    if ($action === 'remove' || $action === 'deactivate') {
        $items = array_values(array_filter($items, static function ($row) use ($studentId): bool {
            return trim((string) ($row['student_id'] ?? '')) !== $studentId;
        }));
        if (!monthly_save_items($items)) {
            Helpers::json(['ok' => false, 'error' => 'Falha ao atualizar cadastro de mensalistas.'], 500);
        }
        Helpers::json(['ok' => true, 'items' => $items]);
    }

    $weeklyDays = (int) ($payload['weekly_days'] ?? 0);
    if (!in_array($weeklyDays, [2, 3, 4, 5], true)) {
        Helpers::json(['ok' => false, 'error' => 'Selecione 2, 3, 4 ou 5 dias por semana.'], 422);
    }

    $client = new SupabaseClient(new HttpClient());
    $studentResult = $client->select(
        'students',
        'select=id,name,enrollment&id=eq.' . urlencode($studentId) . '&limit=1'
    );
    if (!($studentResult['ok'] ?? false) || empty($studentResult['data'][0])) {
        Helpers::json(['ok' => false, 'error' => 'Aluno não encontrado no banco.'], 404);
    }
    $student = $studentResult['data'][0];

    $updated = false;
    foreach ($items as &$row) {
        if (!is_array($row)) {
            continue;
        }
        if (trim((string) ($row['student_id'] ?? '')) !== $studentId) {
            continue;
        }
        $row['student_name'] = trim((string) ($student['name'] ?? ''));
        $row['enrollment'] = trim((string) ($student['enrollment'] ?? ''));
        $row['weekly_days'] = $weeklyDays;
        $row['active'] = true;
        $row['updated_at'] = date('c');
        $row['updated_by'] = (string) ($_SESSION['admin_user'] ?? '');
        $updated = true;
        break;
    }
    unset($row);

    if (!$updated) {
        $items[] = [
            'student_id' => $studentId,
            'student_name' => trim((string) ($student['name'] ?? '')),
            'enrollment' => trim((string) ($student['enrollment'] ?? '')),
            'weekly_days' => $weeklyDays,
            'active' => true,
            'updated_at' => date('c'),
            'updated_by' => (string) ($_SESSION['admin_user'] ?? ''),
        ];
    }

    if (!monthly_save_items($items)) {
        Helpers::json(['ok' => false, 'error' => 'Falha ao salvar mensalista.'], 500);
    }

    Helpers::json(['ok' => true, 'items' => monthly_load_items()]);
} catch (\Throwable $e) {
    error_log('[admin-monthly-students] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    Helpers::json([
        'ok' => false,
        'error' => 'Falha interna ao processar mensalistas.',
        'details' => $e->getMessage(),
    ], 500);
}

