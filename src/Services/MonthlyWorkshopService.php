<?php

namespace App\Services;

use App\HttpClient;
use App\SupabaseClient;

final class MonthlyWorkshopService
{
    private SupabaseClient $client;

    public function __construct(?SupabaseClient $client = null)
    {
        $this->client = $client ?? new SupabaseClient(new HttpClient());
    }

    public function getActivePlan(string $studentId): ?array
    {
        if (!$this->isUuid($studentId)) {
            return null;
        }

        $result = $this->client->select(
            'monthly_student_plans',
            'select=student_id,weekly_days,active,updated_at'
                . '&student_id=eq.' . rawurlencode($studentId)
                . '&active=eq.true'
                . '&limit=1'
        );
        if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
            throw new \RuntimeException('Não foi possível confirmar o plano mensalista do aluno.');
        }
        if (empty($result['data'][0])) {
            return null;
        }
        if (!is_array($result['data'][0])) {
            throw new \RuntimeException('Resposta inválida ao consultar o plano mensalista.');
        }

        $plan = $result['data'][0];
        $weeklyDays = (int) ($plan['weekly_days'] ?? 0);
        if ($weeklyDays < 2 || $weeklyDays > 5) {
            return null;
        }
        $plan['required_slots'] = $weeklyDays * 2;
        return $plan;
    }

    public function getState(string $guardianId, string $studentId, string $referenceMonth): array
    {
        if (!$this->isUuid($guardianId) || !$this->isUuid($studentId)) {
            return ['ok' => false, 'error' => 'Responsável ou aluno inválido.', 'status' => 422];
        }
        if (!$this->isMonthStart($referenceMonth) || $referenceMonth !== self::currentMonth()) {
            return ['ok' => false, 'error' => 'Competência mensal inválida.', 'status' => 422];
        }

        $guardian = $this->client->select(
            'guardians',
            'select=id,student_id'
                . '&id=eq.' . rawurlencode($guardianId)
                . '&student_id=eq.' . rawurlencode($studentId)
                . '&limit=1'
        );
        if (!($guardian['ok'] ?? false) || empty($guardian['data'][0])) {
            return ['ok' => false, 'error' => 'Responsável não vinculado ao aluno.', 'status' => 403];
        }

        $plan = $this->getActivePlan($studentId);
        if ($plan === null) {
            return ['ok' => false, 'error' => 'Aluno não possui plano mensalista ativo.', 'status' => 404];
        }

        $student = $this->client->select(
            'students',
            'select=id,name,enrollment'
                . '&id=eq.' . rawurlencode($studentId)
                . '&limit=1'
        );
        $studentRow = (($student['ok'] ?? false) && is_array($student['data'][0] ?? null))
            ? $student['data'][0]
            : ['id' => $studentId, 'name' => 'Aluno(a)', 'enrollment' => null];

        $monthEnd = date('Y-m-d', strtotime($referenceMonth . ' +1 month -1 day'));
        $catalogResult = $this->client->select(
            'oficina_modular',
            'select=id,nome,descricao,tipo,data_inicio_validade,data_fim_validade,monthly_selection_mode,'
                . 'oficina_modular_horarios(id,dia_semana,hora_inicio,hora_fim)'
                . '&ativa=eq.true'
                . '&descricao=ilike.' . rawurlencode('*[CATALOGO_OM_MENSAL]*')
                . '&data_inicio_validade=lte.' . rawurlencode($monthEnd)
                . '&data_fim_validade=gte.' . rawurlencode($referenceMonth)
                . '&order=nome.asc'
        );
        if (!($catalogResult['ok'] ?? false) || !is_array($catalogResult['data'] ?? null)) {
            return ['ok' => false, 'error' => 'Não foi possível carregar as oficinas do mês.', 'status' => 503];
        }

        $catalog = [];
        foreach ($catalogResult['data'] as $office) {
            if (!is_array($office)) {
                continue;
            }
            $schedules = is_array($office['oficina_modular_horarios'] ?? null)
                ? $office['oficina_modular_horarios']
                : [];
            usort($schedules, static function (array $a, array $b): int {
                $day = ((int) ($a['dia_semana'] ?? 0)) <=> ((int) ($b['dia_semana'] ?? 0));
                return $day !== 0 ? $day : strcmp((string) ($a['hora_inicio'] ?? ''), (string) ($b['hora_inicio'] ?? ''));
            });
            if ($schedules === []) {
                continue;
            }
            $catalog[] = [
                'id' => (string) ($office['id'] ?? ''),
                'name' => trim((string) ($office['nome'] ?? 'Oficina')),
                'description' => $this->publicDescription((string) ($office['descricao'] ?? '')),
                'selection_mode' => (string) ($office['monthly_selection_mode'] ?? 'ALL_MEETINGS'),
                'schedules' => array_values(array_map(static fn(array $schedule): array => [
                    'id' => (string) ($schedule['id'] ?? ''),
                    'weekday' => (int) ($schedule['dia_semana'] ?? 0),
                    'start' => substr((string) ($schedule['hora_inicio'] ?? ''), 0, 5),
                    'end' => substr((string) ($schedule['hora_fim'] ?? ''), 0, 5),
                ], $schedules)),
            ];
        }

        $submissionResult = $this->client->select(
            'monthly_workshop_submissions',
            'select=id,status,confirmed_at,weekly_days_snapshot,required_slots'
                . '&student_id=eq.' . rawurlencode($studentId)
                . '&reference_month=eq.' . rawurlencode($referenceMonth)
                . '&status=eq.CONFIRMED'
                . '&limit=1'
        );
        $submission = (($submissionResult['ok'] ?? false) && is_array($submissionResult['data'][0] ?? null))
            ? $submissionResult['data'][0]
            : null;
        $selectedSlots = [];
        if (is_array($submission) && !empty($submission['id'])) {
            $slotsResult = $this->client->select(
                'monthly_workshop_slots',
                'select=id,oficina_modular_id,horario_id,orientadora,dia_semana,hora_inicio,hora_fim'
                    . '&submission_id=eq.' . rawurlencode((string) $submission['id'])
                    . '&order=dia_semana.asc,hora_inicio.asc'
            );
            if (($slotsResult['ok'] ?? false) && is_array($slotsResult['data'] ?? null)) {
                $selectedSlots = array_values(array_map(static fn(array $slot): array => [
                    'id' => (string) ($slot['id'] ?? ''),
                    'workshop_id' => (string) ($slot['oficina_modular_id'] ?? ''),
                    'schedule_id' => (string) ($slot['horario_id'] ?? ''),
                    'orientation' => (bool) ($slot['orientadora'] ?? false),
                    'weekday' => (int) ($slot['dia_semana'] ?? 0),
                    'start' => substr((string) ($slot['hora_inicio'] ?? ''), 0, 5),
                    'end' => substr((string) ($slot['hora_fim'] ?? ''), 0, 5),
                ], $slotsResult['data']));
            }
        }

        return [
            'ok' => true,
            'student' => $studentRow,
            'plan' => $plan,
            'reference_month' => $referenceMonth,
            'catalog' => $catalog,
            'submission' => $submission,
            'selected_slots' => $selectedSlots,
        ];
    }

    public function confirm(
        string $guardianId,
        string $studentId,
        string $referenceMonth,
        array $choices
    ): array {
        if (
            !$this->isUuid($guardianId)
            || !$this->isUuid($studentId)
            || !$this->isMonthStart($referenceMonth)
            || $referenceMonth !== self::currentMonth()
        ) {
            return ['ok' => false, 'error' => 'Dados da confirmação mensal inválidos.', 'status' => 422];
        }

        $normalized = [];
        foreach ($choices as $choice) {
            if (!is_array($choice)) {
                continue;
            }
            if (($choice['orientadora'] ?? false) === true) {
                $weekday = (int) ($choice['dia_semana'] ?? 0);
                $start = substr(trim((string) ($choice['hora_inicio'] ?? '')), 0, 5);
                if ($weekday < 1 || $weekday > 5 || !in_array($start, ['14:00', '15:40'], true)) {
                    return ['ok' => false, 'error' => 'Horário da Orientadora inválido.', 'status' => 422];
                }
                $normalized[] = [
                    'orientadora' => true,
                    'dia_semana' => $weekday,
                    'hora_inicio' => $start,
                ];
                continue;
            }

            $scheduleId = trim((string) ($choice['horario_id'] ?? ''));
            if (!$this->isUuid($scheduleId)) {
                return ['ok' => false, 'error' => 'Encontro de oficina inválido.', 'status' => 422];
            }
            $normalized[] = ['orientadora' => false, 'horario_id' => $scheduleId];
        }

        $result = $this->client->rpc('confirm_monthly_workshops', [
            'p_guardian_id' => $guardianId,
            'p_student_id' => $studentId,
            'p_reference_month' => $referenceMonth,
            'p_choices' => $normalized,
        ]);
        if (!($result['ok'] ?? false)) {
            return ['ok' => false, 'error' => 'Não foi possível confirmar as oficinas do mês.', 'status' => 503];
        }
        $data = $this->normalizeRpcResult($result['data'] ?? null);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'Resposta inválida ao confirmar oficinas.', 'status' => 503];
        }
        if (($data['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'code' => (string) ($data['code'] ?? 'MONTHLY_CONFIRMATION_FAILED'),
                'error' => (string) ($data['error'] ?? 'Não foi possível confirmar as oficinas.'),
                'status' => 422,
                'details' => $data,
            ];
        }
        return $data;
    }

    public function unlock(string $submissionId, string $adminUserId): array
    {
        if (!$this->isUuid($submissionId) || !$this->isUuid($adminUserId)) {
            return ['ok' => false, 'error' => 'Confirmação ou administrador inválido.', 'status' => 422];
        }
        $result = $this->client->rpc('unlock_monthly_workshops', [
            'p_submission_id' => $submissionId,
            'p_admin_user_id' => $adminUserId,
        ]);
        if (!($result['ok'] ?? false)) {
            return ['ok' => false, 'error' => 'Não foi possível desbloquear a confirmação mensal.', 'status' => 503];
        }
        $data = $this->normalizeRpcResult($result['data'] ?? null);
        if (!is_array($data) || ($data['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'error' => (string) ($data['error'] ?? 'Não foi possível desbloquear a confirmação mensal.'),
                'status' => 422,
            ];
        }
        return $data;
    }

    public static function currentMonth(): string
    {
        return date('Y-m-01');
    }

    private function normalizeRpcResult($data): ?array
    {
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            return $data[0];
        }
        return is_array($data) ? $data : null;
    }

    private function isMonthStart(string $value): bool
    {
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $dt instanceof \DateTimeImmutable
            && $dt->format('Y-m-d') === $value
            && $dt->format('d') === '01';
    }

    private function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
    }

    private function publicDescription(string $description): string
    {
        $lines = preg_split('/\R/', $description) ?: [];
        $visible = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || preg_match('/^\[[A-Z0-9_]+\]/', $trimmed)) {
                continue;
            }
            $visible[] = $trimmed;
        }
        return trim(implode(' ', $visible));
    }
}
