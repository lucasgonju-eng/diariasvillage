<?php
declare(strict_types=1);

namespace App\Admin\Dashboard\Data;

use App\MonthlyStudents;

final class PrincipalFinancialLoader extends AbstractSupabaseDashboardLoader
{
    public const KEY = 'principal_financial';

    public function key(): string
    {
        return self::KEY;
    }

    public function load(array $loadedData): array
    {
        $queuedPending = $this->rows($this->client->select(
            'payments',
            'select=*,students(name,enrollment),guardians(parent_name,email,parent_phone)'
                . '&billing_type=eq.PIX_MANUAL_QUEUE&status=eq.queued&order=created_at.desc&limit=500'
        ));

        $allUnpaidRows = $this->rows($this->client->select(
            'payments',
            'select=*,students(name,enrollment),guardians(parent_name,email,parent_phone)'
                . '&paid_at=is.null&order=created_at.desc&limit=20000'
        ));
        $manualPending = [];
        foreach ($allUnpaidRows as $row) {
            $status = strtolower(trim((string) ($row['status'] ?? '')));
            if (in_array($status, ['paid', 'canceled', 'refunded', 'deleted'], true)) {
                continue;
            }
            if ($status === 'queued' && strtoupper((string) ($row['billing_type'] ?? '')) === 'PIX_MANUAL_QUEUE') {
                continue;
            }
            $manualPending[] = $row;
        }

        $manualPaid = $this->rows($this->client->select(
            'payments',
            'select=*,students(name,enrollment),guardians(parent_name,email,parent_phone)'
                . '&status=eq.paid&order=paid_at.desc&limit=1000'
        ));

        $classification = MonthlyStudents::classifyRowsByQuota(
            array_merge($queuedPending, $manualPending, $manualPaid),
            static function (array $row): array {
                $student = is_array($row['students'] ?? null) ? $row['students'] : [];
                return [
                    'student_id' => trim((string) ($row['student_id'] ?? ($student['id'] ?? ''))),
                    'student_name' => trim((string) ($student['name'] ?? '')),
                    'dates' => MonthlyStudents::extractDatesFromPayment(
                        (string) ($row['daily_type'] ?? ''),
                        (string) ($row['payment_date'] ?? '')
                    ),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            },
            is_array($loadedData['__monthlyById'] ?? null) ? $loadedData['__monthlyById'] : [],
            []
        );

        $visiblePaymentIds = [];
        foreach (($classification['visible'] ?? []) as $visibleRow) {
            if (!is_array($visibleRow)) {
                continue;
            }
            $paymentId = trim((string) ($visibleRow['id'] ?? ''));
            if ($paymentId !== '') {
                $visiblePaymentIds[$paymentId] = true;
            }
        }

        $monthlyMetaByPaymentId = [];
        foreach (($classification['meta'] ?? []) as $paymentId => $meta) {
            if (!is_array($meta) || empty($meta['monthly']) || empty($meta['overflow_dates'])) {
                continue;
            }
            $monthlyMetaByPaymentId[(string) $paymentId] = $meta;
        }

        $queuedPending = $this->onlyVisiblePayments($queuedPending, $visiblePaymentIds);
        $manualPending = $this->onlyVisiblePayments($manualPending, $visiblePaymentIds);

        $registrationRows = $this->loadRegistrationRows();
        $paidRegistrationRows = array_values(array_filter(
            $registrationRows,
            static fn(array $row): bool =>
                !empty($row['paid_at'])
                || strtolower((string) ($row['status'] ?? '')) === 'paid'
        ));
        $pendingRegistrationRows = array_values(array_filter(
            $registrationRows,
            static function (array $row): bool {
                $status = strtolower((string) ($row['status'] ?? 'pending'));
                return $status !== 'canceled' && empty($row['paid_at']) && empty($row['student_id']);
            }
        ));

        $studentName = static fn(array $row): string => (string) (($row['students']['name'] ?? '') ?: '');
        DashboardSort::byStudentName($queuedPending, $studentName);
        DashboardSort::byStudentName($manualPending, $studentName);
        DashboardSort::byStudentName($manualPaid, $studentName);
        DashboardSort::byStudentName(
            $paidRegistrationRows,
            static fn(array $row): string => (string) ($row['student_name'] ?? '')
        );
        DashboardSort::byStudentName(
            $pendingRegistrationRows,
            static fn(array $row): string => (string) ($row['student_name'] ?? '')
        );

        return [
            'queuedPending' => $queuedPending,
            'manualPending' => $manualPending,
            'manualPaid' => $manualPaid,
            'inadimplentesMonthlyMetaById' => $monthlyMetaByPaymentId,
            'pendenciasPagas' => $paidRegistrationRows,
            'pendencias' => $pendingRegistrationRows,
            'valorPendencia' => 77.00,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $payments
     * @param array<string, bool> $visiblePaymentIds
     * @return array<int, array<string, mixed>>
     */
    private function onlyVisiblePayments(array $payments, array $visiblePaymentIds): array
    {
        return array_values(array_filter(
            $payments,
            static function (array $row) use ($visiblePaymentIds): bool {
                $paymentId = trim((string) ($row['id'] ?? ''));
                return $paymentId === '' || isset($visiblePaymentIds[$paymentId]);
            }
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRegistrationRows(): array
    {
        $result = $this->client->select(
            'pendencia_de_cadastro',
            'select=id,student_name,student_id,guardian_name,guardian_cpf,guardian_email,created_at,paid_at,payment_date,'
                . 'access_code,enrollment,asaas_payment_id,asaas_invoice_url,status,canceled_at,cancel_reason'
                . '&status=neq.canceled&order=created_at.desc&limit=500'
        );
        if (!($result['ok'] ?? false)) {
            $result = $this->client->select(
                'pendencia_de_cadastro',
                'select=id,student_name,student_id,guardian_name,guardian_cpf,guardian_email,created_at,paid_at,'
                    . 'payment_date,access_code,enrollment&order=created_at.desc&limit=500'
            );
        }

        return $this->rows($result);
    }
}
