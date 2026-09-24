<?php

namespace App\Support;

/**
 * Reads KadiApi's `suggestions[]` on an unmatched deposit: which customer the
 * money probably belongs to, and why. KadiApi sorts them strongest first.
 *
 * Badge strength follows how reliable each match is:
 *   account_no     — the bill ref is the customer's KK- account number (strong)
 *   payer_phone    — M-Pesa's hashed payer number is the customer's phone (strong)
 *   bill_ref_phone — the payer typed the customer's phone as the account (medium)
 * Any other kind is shown neutrally, because KadiApi may add kinds.
 */
class DepositSuggestion
{
    public const LABELS = [
        'account_no' => 'Account number',
        'payer_phone' => 'Paid from this phone',
        'bill_ref_phone' => 'Typed this phone',
    ];

    public const PAID_FROM_AND_TYPED = "Paid from and typed this customer's phone";

    public const SHARED_NUMBER = 'Shared number';

    /**
     * The strongest suggestion, or null when KadiApi has none.
     *
     * @param  array<string, mixed>  $deposit
     * @return array<string, mixed>|null
     */
    public static function top(array $deposit): ?array
    {
        $first = collect($deposit['suggestions'] ?? [])->first(fn ($suggestion): bool => is_array($suggestion));

        return is_array($first) ? $first : null;
    }

    /**
     * Every match kind on a suggestion, falling back to the single `match` field.
     *
     * @param  array<string, mixed>  $suggestion
     * @return list<string>
     */
    public static function matches(array $suggestion): array
    {
        $matches = is_array($suggestion['matches'] ?? null) && $suggestion['matches'] !== []
            ? $suggestion['matches']
            : [$suggestion['match'] ?? null];

        return array_values(array_unique(array_filter(array_map('strval', array_filter($matches)))));
    }

    /**
     * Badges for a suggestion, strongest first, with a red warning when the number
     * belongs to more than one customer.
     *
     * @param  array<string, mixed>  $suggestion
     * @return list<array{label: string, color: string}>
     */
    public static function badges(array $suggestion): array
    {
        $matches = self::matches($suggestion);
        $badges = [];

        if (($suggestion['ambiguous'] ?? false) === true) {
            $badges[] = ['label' => self::SHARED_NUMBER, 'color' => 'danger'];
        }

        // A player who paid the paybill from their own phone and typed that same number
        // as the account: the common case while STK is blocked until they verify.
        if (in_array('payer_phone', $matches, true) && in_array('bill_ref_phone', $matches, true)) {
            $badges[] = ['label' => self::PAID_FROM_AND_TYPED, 'color' => 'success'];
            $matches = array_values(array_diff($matches, ['payer_phone', 'bill_ref_phone']));
        }

        foreach ($matches as $match) {
            $badges[] = ['label' => self::LABELS[$match] ?? str($match)->replace('_', ' ')->ucfirst()->toString(), 'color' => self::color($match)];
        }

        return $badges;
    }

    public static function color(string $match): string
    {
        return match ($match) {
            'account_no', 'payer_phone' => 'success',
            'bill_ref_phone' => 'warning',
            default => 'gray',
        };
    }

    /**
     * `Name · KK-account` for a suggestion.
     *
     * @param  array<string, mixed>  $suggestion
     */
    public static function customerLabel(array $suggestion): string
    {
        return trim(($suggestion['name'] ?? 'Customer #'.($suggestion['customer_id'] ?? '?'))
            .(filled($suggestion['account_no'] ?? null) ? ' · '.$suggestion['account_no'] : ''));
    }
}
