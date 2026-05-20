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
        if ($number < 1000) {
            return (string) Number::format($number, 0);
        }

        if ($number < 1000000) {
            return Number::format($number / 1000, 2).'k';
        }

        return Number::format($number / 1000000, 2).'M';
    }
}
