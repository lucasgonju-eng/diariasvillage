<?php
require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\MonthlyStudents;
use App\SupabaseClient;

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'GET') {
    Helpers::json(['ok' => true, 'items' => MonthlyStudents::load()]);
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

$items = MonthlyStudents::load();

if ($action === 'remove' || $action === 'deactivate') {
    $items = array_values(array_filter($items, static function ($row) use ($studentId): bool {
        return trim((string) ($row['student_id'] ?? '')) !== $studentId;
    }));
    if (!MonthlyStudents::save($items)) {
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

if (!MonthlyStudents::save($items)) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao salvar mensalista.'], 500);
}

Helpers::json(['ok' => true, 'items' => MonthlyStudents::load()]);

