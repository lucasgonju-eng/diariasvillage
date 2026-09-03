<?php
declare(strict_types=1);

namespace App\Admin\Dashboard\Data;

final class ConfirmedEntriesLoader extends AbstractSupabaseDashboardLoader
{
    public const KEY = 'confirmed_entries';

    public function key(): string
    {
        return self::KEY;
    }

    public function load(array $loadedData): array
    {
        $payments = $this->rows($this->client->select(
            'payments',
            'select=amount,payment_date,paid_at,created_at,daily_type,billing_type,access_code,students(name,enrollment)'
                . '&status=eq.paid&order=paid_at.desc&limit=200'
        ));
        DashboardSort::byStudentName(
            $payments,
            static fn(array $row): string => (string) (($row['students']['name'] ?? '') ?: '')
        );

        $monthlyEntries = $this->rows($this->client->select(
            'monthly_workshop_entries',
            'select=id,entry_date,status,access_code,created_at,students(name,enrollment),'
                . 'monthly_workshop_slots(orientadora,hora_inicio,oficina_modular(nome))'
                . '&status=eq.CONFIRMED_BY_PLAN'
                . '&entry_date=gte.' . rawurlencode(date('Y-m-01'))
                . '&order=entry_date.asc'
                . '&limit=2000'
        ), true);

        $monthlySubmissions = $this->rows($this->client->select(
            'monthly_workshop_submissions',
            'select=id,reference_month,weekly_days_snapshot,required_slots,status,confirmed_at,unlocked_at,students(name,enrollment)'
                . '&reference_month=eq.' . rawurlencode(date('Y-m-01'))
                . '&order=confirmed_at.desc'
                . '&limit=1000'
        ), true);

        $paidRegistrationRows = $this->loadPaidRegistrationRows();
        DashboardSort::byStudentName(
            $paidRegistrationRows,
            static fn(array $row): string => (string) ($row['student_name'] ?? '')
        );

        return [
            'payments' => $payments,
            'monthlyEntries' => $monthlyEntries,
            'monthlySubmissions' => $monthlySubmissions,
            'pendenciasPagas' => $paidRegistrationRows,
            'valorPendencia' => 77.00,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadPaidRegistrationRows(): array
    {
        $result = $this->client->select(
            'pendencia_de_cadastro',
            'select=id,student_name,student_id,created_at,paid_at,payment_date,access_code,enrollment,status'
                . '&status=neq.canceled&or=(paid_at.not.is.null,status.eq.paid)'
                . '&order=created_at.desc&limit=500'
        );
        if (!($result['ok'] ?? false)) {
            $result = $this->client->select(
                'pendencia_de_cadastro',
                'select=id,student_name,student_id,created_at,paid_at,payment_date,access_code,enrollment'
                    . '&paid_at=not.is.null&order=created_at.desc&limit=500'
            );
        }

        return array_values(array_filter(
            $this->rows($result),
            static fn(array $row): bool =>
                !empty($row['paid_at'])
                || strtolower((string) ($row['status'] ?? '')) === 'paid'
        ));
    }
}
