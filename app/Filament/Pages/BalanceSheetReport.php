<?php

namespace App\Filament\Pages;

use App\Support\Format;
use BackedEnum;

class BalanceSheetReport extends FinanceReportPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Balance Sheet';

    protected static ?int $navigationSort = 12;

    protected ?string $heading = 'Balance Sheet';

    protected function reportKey(): string
    {
        return 'balance-sheet';
    }

    protected function usesPeriod(): bool
    {
        return false;
    }

    protected function summaryStats(array $data): array
    {
        $difference = (float) ($data['difference'] ?? 0);

        return [
            ['label' => 'Cash Held', 'value' => Format::money($data['assets']['total'] ?? 0), 'description' => 'M-Pesa accounts', 'icon' => 'heroicon-m-building-library', 'color' => 'success'],
            ['label' => 'Owed to Customers & Games', 'value' => Format::money($data['liabilities']['total'] ?? 0), 'description' => 'Wallets, escrow, unmatched deposits', 'icon' => 'heroicon-m-user-group', 'color' => 'warning'],
            ['label' => 'House Wallet', 'value' => Format::money($data['house_wallet'] ?? 0), 'description' => 'Booked house income', 'icon' => 'heroicon-m-home', 'color' => 'info'],
            ['label' => 'Difference', 'value' => Format::money($difference), 'description' => 'Cash − owed − house wallet', 'icon' => 'heroicon-m-scale', 'color' => $difference >= 0 ? 'primary' : 'danger'],
        ];
    }

    protected function blocks(array $data): array
    {
        $money = fn ($value): string => Format::money($value);

        return [
            [
                'title' => 'Cash accounts',
                'description' => 'As of '.Format::date($data['as_of'] ?? null).' ('.($data['source'] ?? 'live').')',
                'headers' => ['Type', 'Account', 'Balance', 'Last fetched'],
                'rows' => collect($data['assets']['cash']['accounts'] ?? [])
                    ->map(fn (array $account): array => [
                        strtoupper((string) ($account['type'] ?? '')),
                        (string) ($account['account'] ?? '—'),
                        $money($account['amount'] ?? 0),
                        Format::dateTime($account['as_of'] ?? null),
                    ])
                    ->all(),
            ],
            [
                'title' => 'What is owed',
                'headers' => ['Liability', 'Amount'],
                'rows' => collect($data['liabilities'] ?? [])
                    ->map(fn ($amount, $key): array => [str($key)->replace('_', ' ')->title()->toString(), $money($amount)])
                    ->values()
                    ->all(),
            ],
            [
                'title' => 'Notes',
                'headers' => ['Note'],
                'rows' => collect($data['notes'] ?? [])->map(fn ($note): array => [(string) $note])->all(),
            ],
        ];
    }
}
