<?php

namespace App\Filament\Pages;

use App\Support\Format;
use BackedEnum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

/**
 * Read-only view of the expenses held by the wallet API's finance ledger. GMS
 * keeps its own expense records (Expenses resource); this shows what the API
 * counts against its income statement.
 */
class ApiExpensesReport extends FinanceListReportPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'API Expenses';

    protected static ?int $navigationSort = 24;

    protected ?string $heading = 'Expenses Recorded on the API';

    protected function reportKey(): string
    {
        return 'expenses';
    }

    protected function exportKey(): ?string
    {
        return 'expenses';
    }

    protected function extraFilters(): array
    {
        return [
            'status' => $this->filterValue('status') ?? 'all',
            'category' => $this->filterValue('category'),
        ];
    }

    protected function summaryStats(array $data): array
    {
        $summary = $data['summary'] ?? [];

        return [
            ['label' => 'Active Expenses', 'value' => Format::money($summary['active']['amount'] ?? 0), 'description' => number_format((int) ($summary['active']['entries'] ?? 0)).' entries', 'icon' => 'heroicon-m-receipt-percent', 'color' => 'warning'],
            ['label' => 'Voided', 'value' => Format::money($summary['voided']['amount'] ?? 0), 'description' => number_format((int) ($summary['voided']['entries'] ?? 0)).' entries', 'icon' => 'heroicon-m-x-circle', 'color' => 'gray'],
        ];
    }

    protected function blocks(array $data): array
    {
        return [
            [
                'title' => 'By category',
                'headers' => ['Category', 'Entries', 'Amount'],
                'rows' => collect($data['summary']['by_category'] ?? [])
                    ->map(fn (array $row, $key): array => [
                        str((string) ($row['category'] ?? (is_string($key) ? $key : '—')))->replace('_', ' ')->title()->toString(),
                        number_format((int) ($row['entries'] ?? 0)),
                        Format::money($row['amount'] ?? 0),
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('expense_date')
                ->label('Date')
                ->formatStateUsing(fn ($state): string => Format::date($state)),
            TextColumn::make('category')
                ->badge()
                ->color('info')
                ->formatStateUsing(fn (?string $state): string => str((string) $state)->replace('_', ' ')->title()->toString()),
            TextColumn::make('description')
                ->wrap()
                ->placeholder('—'),
            TextColumn::make('reference')
                ->color('gray')
                ->placeholder('—'),
            TextColumn::make('status')
                ->badge()
                ->color(fn (?string $state): string => $state === 'voided' ? 'gray' : 'success')
                ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state)),
            TextColumn::make('amount')
                ->alignEnd()
                ->weight('bold')
                ->formatStateUsing(fn ($state): string => Format::money($state)),
            TextColumn::make('entered_by')
                ->label('Entered By')
                ->color('gray')
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    protected function tableFilterDefinitions(): array
    {
        return [
            SelectFilter::make('status')->options(['active' => 'Active', 'voided' => 'Voided', 'all' => 'All']),
            SelectFilter::make('category')->options(collect(['mpesa_charges', 'hosting', 'sms', 'salaries', 'marketing', 'licences_and_fees', 'other'])
                ->mapWithKeys(fn (string $key): array => [$key => str($key)->replace('_', ' ')->title()->toString()])
                ->all()),
        ];
    }

    protected function emptyHeading(): string
    {
        return 'No expenses recorded';
    }
}
