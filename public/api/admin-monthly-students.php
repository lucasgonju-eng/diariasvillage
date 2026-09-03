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
use App\MonthlyStudents;
use App\SupabaseClient;

try {
    $admin = Helpers::requireAdminRole(['admin_principal', 'secretaria']);
    $client = new SupabaseClient(new HttpClient());
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        Helpers::json(['ok' => true, 'items' => MonthlyStudents::load()]);
    }

    Helpers::requirePost();
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = [];
    }

    $action = strtolower(trim((string) ($payload['action'] ?? 'set')));
    $studentId = trim((string) ($payload['student_id'] ?? ''));
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $studentId)) {
        Helpers::json(['ok' => false, 'error' => 'Aluno inválido.'], 422);
    }

    $studentResult = $client->select(
        'students',
        'select=id&id=eq.' . rawurlencode($studentId) . '&active=eq.true&limit=1'
    );
    if (!($studentResult['ok'] ?? false) || empty($studentResult['data'][0])) {
        Helpers::json(['ok' => false, 'error' => 'Aluno ativo não encontrado.'], 404);
    }

    $existing = $client->select(
        'monthly_student_plans',
        'select=student_id,weekly_days,active&student_id=eq.' . rawurlencode($studentId) . '&limit=1'
    );
    if (!($existing['ok'] ?? false)) {
        Helpers::json(['ok' => false, 'error' => 'Não foi possível consultar o plano mensalista.'], 503);
    }

    $adminId = trim((string) ($admin['id'] ?? ''));
    $now = date('c');
    $lockedSubmission = $client->select(
        'monthly_workshop_submissions',
        'select=id&student_id=eq.' . rawurlencode($studentId)
            . '&reference_month=eq.' . rawurlencode(date('Y-m-01'))
            . '&status=eq.CONFIRMED'
            . '&limit=1'
    );
    if (!($lockedSubmission['ok'] ?? false)) {
        Helpers::json(['ok' => false, 'error' => 'Não foi possível verificar a confirmação mensal.'], 503);
    }
    $hasLockedSubmission = !empty($lockedSubmission['data'][0]);

    if (in_array($action, ['remove', 'deactivate'], true)) {
        if ($hasLockedSubmission) {
            Helpers::json([
                'ok' => false,
                'code' => 'MONTHLY_SUBMISSION_LOCKED',
                'error' => 'Desbloqueie a confirmação do mês antes de desativar o plano.',
            ], 409);
        }
        if (!empty($existing['data'][0])) {
            $update = $client->update(
                'monthly_student_plans',
                'student_id=eq.' . rawurlencode($studentId),
                [
                    'active' => false,
                    'updated_by' => $adminId !== '' ? $adminId : null,
                    'updated_at' => $now,
                ]
            );
            if (!($update['ok'] ?? false)) {
                Helpers::json(['ok' => false, 'error' => 'Falha ao desativar plano mensalista.'], 500);
            }
        }
        Helpers::json(['ok' => true, 'items' => MonthlyStudents::load()]);
    }

    $weeklyDays = (int) ($payload['weekly_days'] ?? 0);
    if (!in_array($weeklyDays, [2, 3, 4, 5], true)) {
        Helpers::json(['ok' => false, 'error' => 'Selecione 2, 3, 4 ou 5 dias por semana.'], 422);
    }
    $existingPlan = is_array($existing['data'][0] ?? null) ? $existing['data'][0] : null;
    $changesLockedPlan = $hasLockedSubmission
        && is_array($existingPlan)
        && (
            (int) ($existingPlan['weekly_days'] ?? 0) !== $weeklyDays
            || ($existingPlan['active'] ?? true) === false
        );
    if ($changesLockedPlan) {
        Helpers::json([
            'ok' => false,
            'code' => 'MONTHLY_SUBMISSION_LOCKED',
            'error' => 'Desbloqueie a confirmação do mês antes de alterar a franquia do plano.',
        ], 409);
    }

    if (!empty($existing['data'][0])) {
        $save = $client->update(
            'monthly_student_plans',
            'student_id=eq.' . rawurlencode($studentId),
            [
                'weekly_days' => $weeklyDays,
                'active' => true,
                'updated_by' => $adminId !== '' ? $adminId : null,
                'updated_at' => $now,
            ]
        );
    } else {
        $save = $client->insert('monthly_student_plans', [[
            'student_id' => $studentId,
            'weekly_days' => $weeklyDays,
            'active' => true,
            'updated_by' => $adminId !== '' ? $adminId : null,
            'updated_at' => $now,
        ]]);
    }

    if (!($save['ok'] ?? false)) {
        Helpers::json(['ok' => false, 'error' => 'Falha ao salvar plano mensalista.'], 500);
    }

    Helpers::json(['ok' => true, 'items' => MonthlyStudents::load()]);
} catch (\Throwable $e) {
    error_log('[admin-monthly-students] ' . $e->getMessage());
    Helpers::json(['ok' => false, 'error' => 'Falha interna ao processar mensalistas.'], 500);
}
