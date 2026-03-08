<?php

namespace App;

final class AttendanceCalls
{
    public const STATUS_EM_REVISAO = 'em_revisao';
    public const STATUS_AUTORIZADA_COBRANCA = 'autorizada_cobranca';
    public const STATUS_REJEITADA = 'rejeitada';
    public const STATUS_ALUNO_MENSALISTA = 'aluno_mensalista';
    public const STATUS_BLOQUEADA_JA_PAGA = 'bloqueada_ja_paga';
    public const STATUS_BLOQUEADA_DUPLICIDADE = 'bloqueada_duplicidade';
    public const STATUS_ERRO_COBRANCA = 'erro_cobranca';

    /**
     * @return array<int, string>
     */
    public static function finalStatuses(): array
    {
        return [
            self::STATUS_AUTORIZADA_COBRANCA,
            self::STATUS_REJEITADA,
            self::STATUS_ALUNO_MENSALISTA,
            self::STATUS_BLOQUEADA_JA_PAGA,
            self::STATUS_BLOQUEADA_DUPLICIDADE,
            self::STATUS_ERRO_COBRANCA,
        ];
    }

    public static function storagePath(): string
    {
        $projectRoot = dirname(__DIR__);
        $parentRoot = dirname($projectRoot);
        $preferred = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'attendance_calls.json';
        $legacy = $projectRoot . DIRECTORY_SEPARATOR . 'attendance_calls.json';
        $oldWrongPreferred = $parentRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'attendance_calls.json';
        $oldWrongLegacy = $parentRoot . DIRECTORY_SEPARATOR . 'attendance_calls.json';

        $candidates = [$preferred, $legacy, $oldWrongPreferred, $oldWrongLegacy];
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
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

        $rows = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            $studentName = trim((string) ($row['student_name'] ?? ''));
            $attendanceDate = self::normalizeDate((string) ($row['attendance_date'] ?? ''));
            $status = trim((string) ($row['status'] ?? self::STATUS_EM_REVISAO));
            if ($id === '' || $studentName === '' || $attendanceDate === null || $status === '') {
                continue;
            }

            $rows[] = [
                'id' => $id,
                'attendance_date' => $attendanceDate,
                'student_id' => trim((string) ($row['student_id'] ?? '')),
                'student_name' => $studentName,
                'office_id' => trim((string) ($row['office_id'] ?? '')),
                'office_name' => trim((string) ($row['office_name'] ?? '')),
                'office_code' => trim((string) ($row['office_code'] ?? '')),
                'status' => $status,
                'created_at' => (string) ($row['created_at'] ?? ''),
                'created_by_role' => trim((string) ($row['created_by_role'] ?? '')),
                'created_by_user' => trim((string) ($row['created_by_user'] ?? '')),
                'reviewed_at' => (string) ($row['reviewed_at'] ?? ''),
                'reviewed_by' => trim((string) ($row['reviewed_by'] ?? '')),
                'review_note' => trim((string) ($row['review_note'] ?? '')),
                'queue_payment_id' => trim((string) ($row['queue_payment_id'] ?? '')),
                'warning' => trim((string) ($row['warning'] ?? '')),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $normalize = static function (string $value): string {
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
            };

            $aName = $normalize((string) ($a['student_name'] ?? ''));
            $bName = $normalize((string) ($b['student_name'] ?? ''));
            $byName = strcmp($aName, $bName);
            if ($byName !== 0) {
                return $byName;
            }
            $byDate = strcmp((string) ($b['attendance_date'] ?? ''), (string) ($a['attendance_date'] ?? ''));
            if ($byDate !== 0) {
                return $byDate;
            }
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public static function save(array $rows): bool
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            $studentName = trim((string) ($row['student_name'] ?? ''));
            $attendanceDate = self::normalizeDate((string) ($row['attendance_date'] ?? ''));
            if ($id === '' || $studentName === '' || $attendanceDate === null) {
                continue;
            }
            $status = trim((string) ($row['status'] ?? self::STATUS_EM_REVISAO));
            if ($status === '') {
                $status = self::STATUS_EM_REVISAO;
            }
            $normalized[] = [
                'id' => $id,
                'attendance_date' => $attendanceDate,
                'student_id' => trim((string) ($row['student_id'] ?? '')),
                'student_name' => $studentName,
                'office_id' => trim((string) ($row['office_id'] ?? '')),
                'office_name' => trim((string) ($row['office_name'] ?? '')),
                'office_code' => trim((string) ($row['office_code'] ?? '')),
                'status' => $status,
                'created_at' => (string) ($row['created_at'] ?? date('c')),
                'created_by_role' => trim((string) ($row['created_by_role'] ?? '')),
                'created_by_user' => trim((string) ($row['created_by_user'] ?? '')),
                'reviewed_at' => (string) ($row['reviewed_at'] ?? ''),
                'reviewed_by' => trim((string) ($row['reviewed_by'] ?? '')),
                'review_note' => trim((string) ($row['review_note'] ?? '')),
                'queue_payment_id' => trim((string) ($row['queue_payment_id'] ?? '')),
                'warning' => trim((string) ($row['warning'] ?? '')),
            ];
        }

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

    public static function normalizeDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $raw)) {
            [$day, $month, $year] = explode('/', $raw);
            if (!checkdate((int) $month, (int) $day, (int) $year)) {
                return null;
            }
            return sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day);
        }
        return null;
    }

    public static function createId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function findById(array $rows, string $id): ?array
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((string) ($row['id'] ?? '') === $id) {
                return $row;
            }
        }
        return null;
    }
}

