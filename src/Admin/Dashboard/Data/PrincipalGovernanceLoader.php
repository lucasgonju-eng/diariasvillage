<?php
declare(strict_types=1);

namespace App\Admin\Dashboard\Data;

use App\ExclusionLog;

final class PrincipalGovernanceLoader extends AbstractSupabaseDashboardLoader
{
    public const KEY = 'principal_governance';

    public function key(): string
    {
        return self::KEY;
    }

    public function load(array $loadedData): array
    {
        $secretariaAccount = null;
        $accountResult = $this->client->select(
            'admin_users',
            'select=id,active,session_version,requires_password_setup,last_login_at'
                . '&username=eq.secretaria&limit=1'
        );
        if (($accountResult['ok'] ?? false) && is_array($accountResult['data'][0] ?? null)) {
            $secretariaAccount = $accountResult['data'][0];
        }

        $students = is_array($loadedData['__students'] ?? null) ? $loadedData['__students'] : [];
        $guardians = $this->rows($this->client->select(
            'guardians',
            'select=id,student_id,parent_name,email,parent_document,auth_user_id,students(id,name,enrollment)'
                . '&parent_document=not.is.null&order=created_at.desc&limit=10000'
        ));

        $duplicateGroups = $this->duplicateStudentNames($students);
        $duplicateEnrollmentGroups = $this->duplicateEnrollments($students);
        $cpfDuplicateGroups = $this->duplicateGuardianDocuments($guardians);
        $exclusionsLog = $this->loadExclusionsLog();

        $groupStudentName = static fn(array $group): string =>
            (string) (($group[0]['name'] ?? '') ?: '');
        usort($duplicateGroups, static fn(array $a, array $b): int =>
            strcmp(DashboardSort::normalizeName($groupStudentName($a)), DashboardSort::normalizeName($groupStudentName($b)))
        );
        usort($duplicateEnrollmentGroups, static fn(array $a, array $b): int =>
            strcmp(DashboardSort::normalizeName($groupStudentName($a)), DashboardSort::normalizeName($groupStudentName($b)))
        );
        usort($cpfDuplicateGroups, static fn(array $a, array $b): int => strcmp(
            DashboardSort::normalizeName((string) (($a[0]['students']['name'] ?? '') ?: '')),
            DashboardSort::normalizeName((string) (($b[0]['students']['name'] ?? '') ?: ''))
        ));
        usort($exclusionsLog, static function (array $a, array $b): int {
            $comparison = strcmp(
                DashboardSort::normalizeName((string) ($a['student_name'] ?? '')),
                DashboardSort::normalizeName((string) ($b['student_name'] ?? ''))
            );
            if ($comparison !== 0) {
                return $comparison;
            }
            $aDate = strtotime((string) ($a['deleted_at'] ?? '')) ?: 0;
            $bDate = strtotime((string) ($b['deleted_at'] ?? '')) ?: 0;
            return $bDate <=> $aDate;
        });

        return [
            'secretariaAccount' => $secretariaAccount,
            'duplicateGroups' => $duplicateGroups,
            'duplicateEnrollmentGroups' => $duplicateEnrollmentGroups,
            'cpfDuplicateGroups' => $cpfDuplicateGroups,
            'exclusionsLog' => $exclusionsLog,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $students
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function duplicateStudentNames(array $students): array
    {
        $groups = [];
        foreach ($students as $student) {
            if (!is_array($student)) {
                continue;
            }
            $key = $this->compactName((string) ($student['name'] ?? ''));
            if ($key !== '') {
                $groups[$key][] = $student;
            }
        }

        return $this->duplicateGroupsSortedByCreation($groups);
    }

    /**
     * @param array<int, array<string, mixed>> $students
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function duplicateEnrollments(array $students): array
    {
        $groups = [];
        foreach ($students as $student) {
            if (!is_array($student)) {
                continue;
            }
            $enrollment = trim((string) ($student['enrollment'] ?? ''));
            if ($enrollment !== '' && $enrollment !== '-') {
                $groups[$enrollment][] = $student;
            }
        }

        return $this->duplicateGroupsSortedByCreation($groups);
    }

    /**
     * @param array<int, array<string, mixed>> $guardians
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function duplicateGuardianDocuments(array $guardians): array
    {
        $groups = [];
        foreach ($guardians as $guardian) {
            if (!is_array($guardian)) {
                continue;
            }
            $document = trim((string) ($guardian['parent_document'] ?? ''));
            if ($document !== '') {
                $groups[$document][] = $guardian;
            }
        }

        $duplicates = [];
        foreach ($groups as $group) {
            $studentIds = array_unique(array_filter(array_map(
                static fn(array $guardian): string => (string) ($guardian['student_id'] ?? ''),
                $group
            )));
            if (count($studentIds) > 1) {
                $duplicates[] = $group;
            }
        }

        return $duplicates;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $groups
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function duplicateGroupsSortedByCreation(array $groups): array
    {
        $duplicates = [];
        foreach ($groups as $group) {
            if (count($group) < 2) {
                continue;
            }
            usort($group, static fn(array $a, array $b): int =>
                (strtotime((string) ($a['created_at'] ?? '')) ?: 0)
                <=>
                (strtotime((string) ($b['created_at'] ?? '')) ?: 0)
            );
            $duplicates[] = $group;
        }
        return $duplicates;
    }

    private function compactName(string $name): string
    {
        return str_replace(' ', '', DashboardSort::normalizeName($name));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadExclusionsLog(): array
    {
        $rows = [];
        foreach (ExclusionLog::load(500) as $decoded) {
            $rows[] = [
                'deleted_at' => $decoded['deleted_at'] ?? '',
                'entity_type' => $decoded['entity_type'] ?? '',
                'entity_id' => $decoded['entity_id'] ?? '',
                'student_name' => $decoded['student_name'] ?? '',
                'guardian_name' => $decoded['guardian_name'] ?? '',
                'payment_date' => $decoded['payment_date'] ?? '',
                'amount' => $decoded['amount'] ?? null,
                'reason' => $decoded['reason'] ?? '',
                'source' => $decoded['source'] ?? '',
                'notes' => $decoded['notes'] ?? '',
            ];
        }

        return $rows;
    }
}
