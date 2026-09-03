<?php
declare(strict_types=1);

namespace App\Admin\Dashboard\Data;

use App\Helpers;
use App\MonthlyStudents;

final class DashboardViewValues
{
    public static function parseBrDateToIso(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }
        if (preg_match('/^\d{2}\/\d{2}\/\d{2,4}$/', $raw)) {
            [$day, $month, $year] = explode('/', $raw);
            $year = (int) $year;
            if ($year < 100) {
                $year += 2000;
            }
            if (!checkdate((int) $month, (int) $day, $year)) {
                return null;
            }
            return sprintf('%04d-%02d-%02d', $year, (int) $month, (int) $day);
        }

        $time = strtotime($raw);
        return $time === false ? null : date('Y-m-d', $time);
    }

    /**
     * @param array<string, mixed> $paymentRow
     * @return array<int, string>
     */
    public static function extractDayUseIsoDates(array $paymentRow): array
    {
        $dailyRaw = trim((string) ($paymentRow['daily_type'] ?? ''));
        $datesLabel = '';
        if ($dailyRaw !== '') {
            $parts = explode('|', $dailyRaw, 2);
            $datesLabel = trim((string) ($parts[1] ?? ''));
        }

        $dates = [];
        if ($datesLabel !== '') {
            foreach (explode(',', $datesLabel) as $chunk) {
                $iso = self::parseBrDateToIso((string) $chunk);
                if ($iso !== null) {
                    $dates[$iso] = true;
                }
            }
        }
        if ($dates === []) {
            $fallbackIso = self::parseBrDateToIso((string) ($paymentRow['payment_date'] ?? ''));
            if ($fallbackIso !== null) {
                $dates[$fallbackIso] = true;
            }
        }

        $result = array_keys($dates);
        sort($result);
        return $result;
    }

    /**
     * @param array<string, mixed> $paymentRow
     */
    public static function resolveOpenAmount(array $paymentRow): float
    {
        $dates = self::extractDayUseIsoDates($paymentRow);
        if ($dates === []) {
            return (float) ($paymentRow['amount'] ?? 0);
        }

        $total = 0.0;
        foreach ($dates as $isoDate) {
            $rule = Helpers::resolveDayUseCharge($isoDate);
            $total += (float) ($rule['amount'] ?? 77.0);
        }
        return $total;
    }

    /**
     * @param array<string, mixed> $paymentRow
     */
    public static function isFebruaryChargeOnly(array $paymentRow): bool
    {
        $dates = self::extractDayUseIsoDates($paymentRow);
        if ($dates === []) {
            return false;
        }
        foreach ($dates as $isoDate) {
            if (substr($isoDate, 5, 2) !== '02') {
                return false;
            }
        }
        return true;
    }

    public static function isIsabelVoucherStudent(string $studentName): bool
    {
        $normalized = MonthlyStudents::normalizeText($studentName);
        return $normalized !== '' && str_starts_with($normalized, 'ISABELGONCALVESRAUEN');
    }

    public static function voucherLabelFromBillingType(string $billingType): string
    {
        if (preg_match('/VOUCHER_ISABEL_(\d{1,2})_30/', $billingType, $match)) {
            return 'Voucher ' . (int) $match[1] . '/30';
        }
        return '';
    }
}
