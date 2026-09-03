<?php
declare(strict_types=1);

use App\Admin\Dashboard\Data\DashboardViewValues;

function parseBrDateToIso(string $raw): ?string
{
    return DashboardViewValues::parseBrDateToIso($raw);
}

/**
 * @param array<string, mixed> $paymentRow
 * @return array<int, string>
 */
function extractDayUseIsoDatesFromPaymentRow(array $paymentRow): array
{
    return DashboardViewValues::extractDayUseIsoDates($paymentRow);
}

/**
 * @param array<string, mixed> $paymentRow
 */
function resolveOpenAmountFromPaymentRow(array $paymentRow): float
{
    return DashboardViewValues::resolveOpenAmount($paymentRow);
}

/**
 * @param array<string, mixed> $paymentRow
 */
function isFebruaryChargeOnly(array $paymentRow): bool
{
    return DashboardViewValues::isFebruaryChargeOnly($paymentRow);
}

function isIsabelVoucherStudent(string $studentName): bool
{
    return DashboardViewValues::isIsabelVoucherStudent($studentName);
}

function voucherLabelFromBillingType(string $billingType): string
{
    return DashboardViewValues::voucherLabelFromBillingType($billingType);
}
