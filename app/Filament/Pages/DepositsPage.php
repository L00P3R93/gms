<?php

namespace App\Filament\Pages;

use App\Support\Format;
use BackedEnum;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use UnitEnum;

class DepositsPage extends FinanceListReportPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationLabel = 'Deposits';

    protected static string|UnitEnum|null $navigationGroup = '📊 Financial';

    protected static ?int $navigationSort = 1;

    protected ?string $heading = 'Deposits';

    protected function reportKey(): string
    {
        return 'deposits';
    }

    protected function exportKey(): ?string
    {
        return 'deposits';
    }

    protected function extraFilters(): array
    {
        return [
            'status' => $this->filterValue('status'),
            'kind' => $this->filterValue('kind'),
        ];
    }

    protected function summaryStats(array $data): array
    {
        $summary = $data['summary'] ?? [];
        $unmatched = $summary['by_status']['unmatched'] ?? [];
        $top = $summary['top_depositors'][0] ?? null;

        return [
            ['label' => 'Payments Received', 'value' => number_format((int) ($summary['payments'] ?? 0)), 'description' => 'In the selected period', 'icon' => 'heroicon-m-arrow-down-tray', 'color' => 'primary'],
            ['label' => 'Total Received', 'value' => Format::money($summary['amount'] ?? 0), 'icon' => 'heroicon-m-banknotes', 'color' => 'success'],
            ['label' => 'Unmatched Deposits', 'value' => Format::money($unmatched['amount'] ?? 0), 'description' => number_format((int) ($unmatched['payments'] ?? 0)).' payments not credited to a player', 'icon' => 'heroicon-m-exclamation-triangle', 'color' => ($unmatched['payments'] ?? 0) > 0 ? 'warning' : 'gray'],
            ['label' => 'Top Depositor', 'value' => $top ? Format::money($top['amount'] ?? 0) : '—', 'description' => $top['customer_name'] ?? 'No matched deposits', 'icon' => 'heroicon-m-trophy', 'color' => 'info'],
        ];
    }

    protected function blocks(array $data): array
    {
        return [
            [
                'title' => 'By kind',
                'headers' => ['Kind', 'Payments', 'Amount'],
                'rows' => collect($data['summary']['by_kind'] ?? [])
                    ->map(fn (array $row, string $kind): array => [str($kind)->replace('_', ' ')->title()->toString(), number_format((int) ($row['payments'] ?? 0)), Format::money($row['amount'] ?? 0)])
                    ->values()
                    ->all(),
            ],
        ];
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('created_at')
                ->label('Date')
                ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
            TextColumn::make('trans_id')
                ->label('M-Pesa Ref')
                ->copyable(),
            TextColumn::make('customer_name')
                ->label('Player')
                ->weight('bold')
                ->placeholder('Unmatched'),
            TextColumn::make('bill_ref_no')
                ->label('Account Ref')
                ->color('gray')
                ->size(TextSize::Small),
            TextColumn::make('kind')
                ->label('Kind')
                ->badge()
                ->color(fn (?string $state): string => $state === 'unmatched' ? 'warning' : 'info')
                ->formatStateUsing(fn (?string $state): string => str((string) $state)->replace('_', ' ')->title()->toString()),
            TextColumn::make('status')
                ->badge()
                ->color(fn (?string $state): string => match ($state) {
                    'processed' => 'success',
                    'pending' => 'warning',
                    'unmatched' => 'danger',
                    default => 'gray',
                })
                ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state)),
            TextColumn::make('amount')
                ->label('Amount')
                ->weight('bold')
                ->alignEnd()
                ->formatStateUsing(fn ($state): string => Format::money($state)),
        ];
    }

    protected function tableFilterDefinitions(): array
    {
        return [
            SelectFilter::make('status')->options(['0' => 'Unmatched', '1' => 'Pending', '2' => 'Processed']),
            SelectFilter::make('kind')->options([
                'wallet_deposit' => 'Wallet deposit',
                'load' => 'Load',
                'gift' => 'Gift',
                'emoji' => 'Emoji',
                'unmatched' => 'Unmatched',
            ]),
        ];
    }

    protected function emptyHeading(): string
    {
        return 'No deposits found';
    }
}
