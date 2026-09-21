<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/**
 * Shared display formatting for GameApi-backed Filament pages. Every page must
 * route money, dates, and phone numbers through these helpers so formatting
 * stays identical panel-wide.
 *
 * Badge colour vocabulary — apply consistently on every page:
 *   success — active, settled, won, completed, verified
 *   warning — pending, processing, in-progress, top-tier highlight
 *   danger  — failed, suspended, hidden, rejected
 *   info    — neutral classification tags (type / level / referral code)
 *   gray    — archived, inactive, unknown
 */
class Format
{
    /**
     * Format a monetary amount as `KES 1,234.00`.
     */
    public static function money(int|float|string|null $amount): string
    {
        return 'KES '.number_format((float) ($amount ?? 0), 2);
    }

    /**
     * Format an API date value as `05 May 2026`, or a dash when empty/invalid.
     */
    public static function date(int|string|null $value): string
    {
        return self::carbon($value)?->format('d M Y') ?? '—';
    }

    /**
     * Format an API datetime value as `05 May 2026, 14:30`, or a dash.
     */
    public static function dateTime(int|string|null $value): string
    {
        return self::carbon($value)?->format('d M Y, H:i') ?? '—';
    }

    /**
     * Mask all but the last four digits of a phone number.
     */
    public static function maskedPhone(int|string|null $phone): string
    {
        $phone = (string) $phone;

        return $phone === '' ? '—' : '****'.substr($phone, -4);
    }

    protected static function carbon(int|string|null $value): ?Carbon
    {
        if ($value === null || $value === '' || $value === 0) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Format a number with suffixes (e.g. 1k, 1.2M).
     */
    public static function formatNumber(int $number): string
    {
        $sign = $number < 0 ? '-' : '';
        $magnitude = abs($number);

        if ($magnitude < 1000) {
            return (string) Number::format($number, 0);
        }

        if ($magnitude < 1000000) {
            return $sign.Number::format($magnitude / 1000, 2).'k';
        }

        return $sign.Number::format($magnitude / 1000000, 2).'M';
    }

    /**
     * Simplified figure for dashboard widgets: {@see formatNumber()} on the whole
     * number, or a dash when the value is null, empty or an integer zero.
     */
    public static function compact(int|float|string|null $value): string
    {
        return $value !== null && $value !== '' && $value !== 0 ? self::formatNumber((int) $value) : '—';
    }

    /**
     * {@see compact()} prefixed with the currency, e.g. `KES 1.85M`.
     */
    public static function compactMoney(int|float|string|null $value): string
    {
        return 'KES '.self::compact($value);
    }
}
