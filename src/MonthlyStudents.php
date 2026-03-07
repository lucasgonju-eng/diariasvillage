<?php

namespace App;

class MonthlyStudents
{
    public static function storagePath(): string
    {
        $projectRoot = dirname(__DIR__);
        $preferred = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'monthly_students.json';
        $legacy = $projectRoot . DIRECTORY_SEPARATOR . 'monthly_students.json';

        if (is_file($preferred)) {
            return $preferred;
        }
        if (is_file($legacy)) {
            return $legacy;
        }

        return $preferred;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function load(): array
    {
        $path = self::storagePath();
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
            if ($studentId === '') {
                continue;
            }
            $weeklyDays = (int) ($row['weekly_days'] ?? 0);
            if (!in_array($weeklyDays, [2, 3, 4, 5], true)) {
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

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public static function save(array $items): bool
    {
        $normalized = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $studentId = trim((string) ($row['student_id'] ?? ''));
            if ($studentId === '') {
                continue;
            }
            $weeklyDays = (int) ($row['weekly_days'] ?? 0);
            if (!in_array($weeklyDays, [2, 3, 4, 5], true)) {
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

        $path = self::storagePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return false;
            }
        }

        return @file_put_contents($path, $json . PHP_EOL, LOCK_EX) !== false;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, array<string, mixed>>
     */
    public static function mapByStudentId(array $items): array
    {
        $map = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $studentId = trim((string) ($row['student_id'] ?? ''));
            if ($studentId === '' || ($row['active'] ?? true) === false) {
                continue;
            }
            $map[$studentId] = $row;
        }
        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, array<string, mixed>>
     */
    public static function mapByNormalizedName(array $items): array
    {
        $map = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (($row['active'] ?? true) === false) {
                continue;
            }
            $name = self::normalizeText((string) ($row['student_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $map[$name] = $row;
        }
        return $map;
    }

    public static function resolvePlan(?string $studentId, ?string $studentName, array $plansById, array $plansByName): ?array
    {
        $id = trim((string) $studentId);
        if ($id !== '' && isset($plansById[$id])) {
            return $plansById[$id];
        }
        $nameKey = self::normalizeText((string) $studentName);
        if ($nameKey !== '' && isset($plansByName[$nameKey])) {
            return $plansByName[$nameKey];
        }
        return null;
    }

    public static function normalizeText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_strtoupper')) {
            $value = mb_strtoupper($value, 'UTF-8');
        } else {
            $value = strtoupper($value);
        }
        $translit = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        if ($translit !== false) {
            $value = $translit;
        }
        $value = preg_replace('/[^A-Z0-9]+/', '', $value) ?? '';
        return trim($value);
    }

    /**
     * @return array<int, string>
     */
    public static function extractDatesFromPayment(string $dailyType, ?string $fallbackDate): array
    {
        $dates = [];
        $parts = explode('|', $dailyType, 2);
        $datesRaw = trim((string) ($parts[1] ?? ''));
        if ($datesRaw !== '') {
            $normalized = str_replace(["\r\n", "\n", "\r", ';', '+'], ',', $datesRaw);
            $tokens = array_map('trim', explode(',', $normalized));
            foreach ($tokens as $token) {
                $parsed = self::parseFlexibleDate($token);
                if ($parsed !== null) {
                    $dates[$parsed] = true;
                }
            }
        }
        $fallback = self::parseFlexibleDate((string) $fallbackDate);
        if ($fallback !== null) {
            $dates[$fallback] = true;
        }

        return array_keys($dates);
    }

    public static function parseFlexibleDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^\d{2}\/\d{2}\/\d{2,4}$/', $value)) {
            [$day, $month, $year] = explode('/', $value);
            $yearInt = (int) $year;
            if ($yearInt < 100) {
                $yearInt += 2000;
            }
            if (!checkdate((int) $month, (int) $day, $yearInt)) {
                return null;
            }
            return sprintf('%04d-%02d-%02d', $yearInt, (int) $month, (int) $day);
        }

        $time = strtotime($value);
        if ($time === false) {
            return null;
        }
        return date('Y-m-d', $time);
    }

    public static function weekKey(string $isoDate): string
    {
        try {
            $dt = new \DateTimeImmutable($isoDate . ' 00:00:00');
            return $dt->format('o-\WW');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param callable $resolver function(array $row): array{student_id:string,student_name:string,dates:array<int,string>,created_at:string}
     * @return array{visible: array<int, array<string,mixed>>, covered: array<int, array<string,mixed>>, meta: array<string, array<string,mixed>>}
     */
    public static function classifyRowsByQuota(array $rows, callable $resolver, array $plansById, array $plansByName): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $resolved = $resolver($row);
            if (!is_array($resolved)) {
                continue;
            }
            $dates = array_values(array_filter(array_map(static fn($d) => self::parseFlexibleDate((string) $d), $resolved['dates'] ?? [])));
            if (empty($dates)) {
                continue;
            }
            sort($dates);
            $indexed[] = [
                'row' => $row,
                'resolved' => [
                    'student_id' => trim((string) ($resolved['student_id'] ?? '')),
                    'student_name' => trim((string) ($resolved['student_name'] ?? '')),
                    'dates' => $dates,
                    'created_at' => (string) ($resolved['created_at'] ?? ''),
                ],
            ];
        }

        usort($indexed, static function (array $a, array $b): int {
            $ad = $a['resolved']['dates'][0] ?? '';
            $bd = $b['resolved']['dates'][0] ?? '';
            if ($ad !== $bd) {
                return strcmp($ad, $bd);
            }
            return strcmp((string) ($a['resolved']['created_at'] ?? ''), (string) ($b['resolved']['created_at'] ?? ''));
        });

        $usage = [];
        $visible = [];
        $covered = [];
        $meta = [];
        foreach ($indexed as $entry) {
            $row = $entry['row'];
            $resolved = $entry['resolved'];
            $plan = self::resolvePlan(
                (string) ($resolved['student_id'] ?? ''),
                (string) ($resolved['student_name'] ?? ''),
                $plansById,
                $plansByName
            );
            if (!is_array($plan)) {
                $visible[] = $row;
                continue;
            }
            $weeklyDays = (int) ($plan['weekly_days'] ?? 0);
            if (!in_array($weeklyDays, [2, 3, 4, 5], true)) {
                $visible[] = $row;
                continue;
            }

            $studentKey = trim((string) ($plan['student_id'] ?? ''));
            if ($studentKey === '') {
                $studentKey = self::normalizeText((string) ($resolved['student_name'] ?? ''));
            }
            if ($studentKey === '') {
                $visible[] = $row;
                continue;
            }

            $coveredDates = [];
            $overflowDates = [];
            foreach ($resolved['dates'] as $date) {
                $week = self::weekKey((string) $date);
                if ($week === '') {
                    $overflowDates[] = $date;
                    continue;
                }
                if (!isset($usage[$studentKey])) {
                    $usage[$studentKey] = [];
                }
                if (!isset($usage[$studentKey][$week])) {
                    $usage[$studentKey][$week] = [];
                }
                if (isset($usage[$studentKey][$week][$date])) {
                    $coveredDates[] = $date;
                    continue;
                }

                $currentCount = count($usage[$studentKey][$week]);
                if ($currentCount < $weeklyDays) {
                    $coveredDates[] = $date;
                } else {
                    $overflowDates[] = $date;
                }
                $usage[$studentKey][$week][$date] = true;
            }

            $rowId = trim((string) ($row['id'] ?? ''));
            if ($rowId === '') {
                $rowId = md5(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: uniqid('row_', true));
            }
            $meta[$rowId] = [
                'monthly' => true,
                'weekly_days' => $weeklyDays,
                'covered_dates' => $coveredDates,
                'overflow_dates' => $overflowDates,
                'student_name' => (string) ($resolved['student_name'] ?? ''),
                'student_id' => (string) ($resolved['student_id'] ?? ''),
            ];

            if (count($overflowDates) === 0) {
                $covered[] = $row;
            } else {
                $visible[] = $row;
            }
        }

        return [
            'visible' => $visible,
            'covered' => $covered,
            'meta' => $meta,
        ];
    }

    /**
     * @param array<int, array<string,mixed>> $existingPayments
     * @return array<string, array<string, bool>>
     */
    public static function collectUsedDatesByWeek(array $existingPayments): array
    {
        $used = [];
        foreach ($existingPayments as $row) {
            if (!is_array($row)) {
                continue;
            }
            $status = strtolower(trim((string) ($row['status'] ?? '')));
            if (in_array($status, ['canceled', 'refunded', 'deleted'], true)) {
                continue;
            }
            $dates = self::extractDatesFromPayment(
                (string) ($row['daily_type'] ?? ''),
                (string) ($row['payment_date'] ?? '')
            );
            foreach ($dates as $date) {
                $week = self::weekKey($date);
                if ($week === '') {
                    continue;
                }
                if (!isset($used[$week])) {
                    $used[$week] = [];
                }
                $used[$week][$date] = true;
            }
        }
        return $used;
    }

    /**
     * @param array<int, string> $requestedDatesIso
     * @param array<string, array<string, bool>> $usedByWeek
     * @return array{covered: array<int,string>, overflow: array<int,string>}
     */
    public static function splitRequestedDatesByQuota(array $requestedDatesIso, int $weeklyDays, array $usedByWeek): array
    {
        $covered = [];
        $overflow = [];
        foreach ($requestedDatesIso as $date) {
            $dateIso = self::parseFlexibleDate((string) $date);
            if ($dateIso === null) {
                continue;
            }
            $week = self::weekKey($dateIso);
            if ($week === '') {
                $overflow[] = $dateIso;
                continue;
            }
            if (!isset($usedByWeek[$week])) {
                $usedByWeek[$week] = [];
            }
            if (isset($usedByWeek[$week][$dateIso])) {
                $covered[] = $dateIso;
                continue;
            }
            if (count($usedByWeek[$week]) < $weeklyDays) {
                $covered[] = $dateIso;
            } else {
                $overflow[] = $dateIso;
            }
            $usedByWeek[$week][$dateIso] = true;
        }

        return [
            'covered' => array_values(array_unique($covered)),
            'overflow' => array_values(array_unique($overflow)),
        ];
    }
}

