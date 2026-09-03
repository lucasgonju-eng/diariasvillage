<?php
declare(strict_types=1);

namespace App\Admin\Dashboard\Data;

final class DashboardSort
{
    public static function normalizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $name = function_exists('mb_strtoupper')
            ? mb_strtoupper($name, 'UTF-8')
            : strtoupper($name);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
        if ($transliterated !== false) {
            $name = $transliterated;
        }

        return trim(preg_replace('/[^A-Z0-9 ]+/', '', $name) ?? '');
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param callable(array<string, mixed>): string $resolver
     */
    public static function byStudentName(array &$items, callable $resolver): void
    {
        usort($items, static function (array $a, array $b) use ($resolver): int {
            $comparison = strcmp(
                self::normalizeName($resolver($a)),
                self::normalizeName($resolver($b))
            );
            if ($comparison !== 0) {
                return $comparison;
            }

            $aDate = strtotime((string) ($a['created_at'] ?? $a['paid_at'] ?? $a['payment_date'] ?? '')) ?: 0;
            $bDate = strtotime((string) ($b['created_at'] ?? $b['paid_at'] ?? $b['payment_date'] ?? '')) ?: 0;
            return $bDate <=> $aDate;
        });
    }
}
