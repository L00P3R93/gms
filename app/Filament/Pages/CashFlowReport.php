<?php

namespace App\Filament\Pages;

use App\Support\Format;
use BackedEnum;

class CashFlowReport extends FinanceReportPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Cash Flow';

    protected static ?int $navigationSort = 11;

    protected ?string $heading = 'Cash Flow';

    protected function reportKey(): string
    {
        return 'cash-flow';
    }

    protected function exportKey(): ?string
    {
        return 'cash-flow';
    }

    protected function summaryStats(array $data): array
    {
        $totals = $data['totals'] ?? [];

        return [
            ['label' => 'Cash In', 'value' => Format::money($totals['cash_in']['total'] ?? 0), 'description' => 'M-Pesa payments received', 'icon' => 'heroicon-m-arrow-down-tray', 'color' => 'success'],
            ['label' => 'Cash Out (Paid)', 'value' => Format::money($totals['cash_out']['paid'] ?? 0), 'description' => 'Withdrawals paid to players', 'icon' => 'heroicon-m-arrow-up-tray', 'color' => 'info'],
            ['label' => 'Pending Payouts', 'value' => Format::money($totals['cash_out']['pending'] ?? 0), 'description' => 'Awaiting M-Pesa confirmation', 'icon' => 'heroicon-m-clock', 'color' => 'warning'],
            ['label' => 'Net Cash', 'value' => Format::money($totals['net_cash'] ?? 0), 'description' => 'Cash in less cash out', 'icon' => 'heroicon-m-banknotes', 'color' => ($totals['net_cash'] ?? 0) >= 0 ? 'primary' : 'danger'],
        ];
    }

    protected function blocks(array $data): array
    {
        $totals = $data['totals'] ?? [];
        $money = fn ($value): string => Format::money($value);
        $label = fn (string $key): string => str($key)->replace('_', ' ')->title()->toString();

        return [
            [
                'title' => 'Cash in by source',
                'headers' => ['Source', 'Amount'],
                'rows' => collect($totals['cash_in'] ?? [])
                    ->map(fn ($amount, $source): array => [$label($source), $money($amount)])
                    ->values()
                    ->all(),
            ],
            [
                'title' => 'Cash out by status',
                'headers' => ['Status', 'Amount'],
                'rows' => collect($totals['cash_out'] ?? [])
                    ->map(fn ($amount, $status): array => [$label($status), $money($amount)])
                    ->values()
                    ->all(),
            ],
            [
                'title' => 'Trend',
                'description' => 'Per '.($data['meta']['period']['group_by'] ?? 'day'),
                'headers' => ['Period', 'Cash in', 'Paid out', 'Pending', 'Failed', 'Net cash'],
                'rows' => collect($data['series'] ?? [])
                    ->map(fn (array $row): array => [
                        $row['period'] ?? '—',
                        $money($row['cash_in']['total'] ?? 0),
                        $money($row['cash_out']['paid'] ?? 0),
                        $money($row['cash_out']['pending'] ?? 0),
                        $money($row['cash_out']['failed'] ?? 0),
                        $money($row['net_cash'] ?? 0),
                    ])
                    ->all(),
            ],
        ];
    }
}
