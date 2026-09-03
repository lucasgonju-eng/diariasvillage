<?php
declare(strict_types=1);

namespace App\Admin\Dashboard\Data;

final class FamiliesLoader extends AbstractSupabaseDashboardLoader
{
    public const KEY = 'families';

    public function key(): string
    {
        return self::KEY;
    }

    public function load(array $loadedData): array
    {
        $students = $this->rows($this->client->select(
            'students',
            'select=id,name,enrollment,created_at,active&limit=10000'
        ));

        $studentsById = [];
        $studentsByEnrollment = [];
        foreach ($students as $student) {
            $studentId = trim((string) ($student['id'] ?? ''));
            if ($studentId === '') {
                continue;
            }

            $studentsById[$studentId] = $student;
            $enrollment = mb_strtoupper(trim((string) ($student['enrollment'] ?? '')), 'UTF-8');
            if ($enrollment !== '') {
                $studentsByEnrollment[$enrollment][] = $student;
            }
        }

        $studentsForJs = array_map(static function (array $student): array {
            return [
                'id' => (string) ($student['id'] ?? ''),
                'name' => (string) ($student['name'] ?? ''),
                'enrollment' => $student['enrollment'] ?? null,
                'grade' => isset($student['grade']) ? (int) $student['grade'] : null,
                'class_name' => $student['class_name'] ?? null,
            ];
        }, $students);

        $requestsResult = $this->client->selectAll(
            'family_link_requests',
            'select=id,requester_guardian_id,source_student_id,requested_enrollment,target_student_id,status,requested_at'
                . '&status=eq.PENDING&order=requested_at.asc'
        );
        $familyLinkRequests = $this->rows($requestsResult, true);

        $requesterIds = array_values(array_unique(array_filter(array_map(
            static fn(array $request): string => trim((string) ($request['requester_guardian_id'] ?? '')),
            $familyLinkRequests
        ))));
        $guardians = [];
        if ($requesterIds !== []) {
            $guardians = $this->rows($this->client->select(
                'guardians',
                'select=id,parent_name,parent_document'
                    . '&id=in.(' . implode(',', array_map('rawurlencode', $requesterIds)) . ')'
                    . '&limit=' . count($requesterIds)
            ));
        }
        $guardiansById = [];
        foreach ($guardians as $guardian) {
            $guardianId = trim((string) ($guardian['id'] ?? ''));
            if ($guardianId !== '') {
                $guardiansById[$guardianId] = $guardian;
            }
        }

        return [
            'studentsById' => $studentsById,
            'studentsByEnrollment' => $studentsByEnrollment,
            'studentsForJs' => $studentsForJs,
            'guardiansById' => $guardiansById,
            'familyLinkRequests' => $familyLinkRequests,
            '__students' => $students,
        ];
    }
}
