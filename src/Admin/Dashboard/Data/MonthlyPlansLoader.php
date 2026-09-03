<?php
declare(strict_types=1);

namespace App\Admin\Dashboard\Data;

use App\MonthlyStudents;

final class MonthlyPlansLoader implements DashboardDomainLoader
{
    public const KEY = 'monthly_plans';

    public function key(): string
    {
        return self::KEY;
    }

    public function load(array $loadedData): array
    {
        $items = MonthlyStudents::load();
        $rowsForJs = array_values(array_map(static function (array $row): array {
            return [
                'student_id' => (string) ($row['student_id'] ?? ''),
                'student_name' => (string) ($row['student_name'] ?? ''),
                'enrollment' => (string) ($row['enrollment'] ?? ''),
                'weekly_days' => (int) ($row['weekly_days'] ?? 0),
                'active' => ($row['active'] ?? true) !== false,
                'updated_at' => (string) ($row['updated_at'] ?? ''),
                'updated_by' => (string) ($row['updated_by'] ?? ''),
            ];
        }, $items));

        return [
            'monthlyRowsForJs' => $rowsForJs,
            '__monthlyById' => MonthlyStudents::mapByStudentId($items),
        ];
    }
}
