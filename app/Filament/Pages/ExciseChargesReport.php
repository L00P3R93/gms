<?php

namespace App\Filament\Pages;

use App\Support\Format;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;

/**
 * One row per excise duty charge, from a deposit or a signup bonus (`source`),
 * with whether the duty was reversed on a refund or already remitted to KRA.
 * Promotion charges have no deposit, M-Pesa code or payer number.
 */
class ExciseChargesReport extends FinanceListReportPage
{
    public const SOURCES = [
        'deposit' => 'Deposit',
        'promotion' => 'Signup bonus',
    ];

    public const STATUSES = [
        'charged' => 'Charged',
        'unremitted' => 'Unremitted',
        'remitted' => 'Remitted',
        'reversed' => 'Reversed',
    ];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Excise Charges';

    protected static ?int $navigationSort = 18;

    protected ?string $heading = 'Excise Duty Charges';

    protected function reportKey(): string
    {
        return 'excise-duty/charges';
    }

    protected function exportKey(): ?string
    {
        return 'excise-duty-charges';
    }

    protected function extraFilters(): array
    {
        return [
            'status' => $this->filterValue('status'),
            'customer_id' => $this->tableFilters['customer']['customer_id'] ?? null,
        ];
    }

    protected function summaryStats(array $data): array
    {
        $summary = $data['summary'] ?? [];
        $unremitted = $summary['unremitted'] ?? [];

        return [
            ['label' => 'Excise Charged', 'value' => Format::money($summary['excise'] ?? 0), 'description' => number_format((int) ($summary['charges'] ?? 0)).' charges on deposits and signup bonuses', 'icon' => 'heroicon-m-receipt-percent', 'color' => 'info'],
            ['label' => 'Gross Deposits', 'value' => Format::money($summary['gross_deposits'] ?? 0), 'icon' => 'heroicon-m-banknotes', 'color' => 'primary'],
            ['label' => 'Unremitted', 'value' => Format::money($unremitted['excise'] ?? 0), 'description' => number_format((int) ($unremitted['charges'] ?? 0)).' charges not yet paid to KRA', 'icon' => 'heroicon-m-clock', 'color' => ($unremitted['excise'] ?? 0) > 0 ? 'warning' : 'success'],
        ];
    }

    protected function blocks(array $data): array
    {
        return [
            [
                'title' => 'By status',
                'headers' => ['Status', 'Charges', 'Gross deposits', 'Excise'],
                'rows' => collect($data['summary']['by_status'] ?? [])
                    ->filter(fn ($row): bool => is_array($row))
                    ->map(fn (array $row, string $status): array => [
                        self::STATUSES[$status] ?? ucfirst($status),
                        number_format((int) ($row['charges'] ?? 0)),
                        Format::money($row['gross_deposits'] ?? 0),
                        Format::money($row['excise'] ?? 0),
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('charged_at')
                ->label('Charged')
                ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
            TextColumn::make('source')
                ->badge()
                ->color(fn (?string $state): string => $state === 'promotion' ? 'warning' : 'gray')
                ->formatStateUsing(fn (?string $state): string => self::SOURCES[$state] ?? ucfirst((string) ($state ?? 'deposit'))),
            TextColumn::make('trans_id')
                ->label('M-Pesa Code')
                ->fontFamily('mono')
                ->copyable()
                ->placeholder('—'),
            TextColumn::make('customer_name')
                ->label('Player')
                ->weight('bold')
                ->description(fn (array $record): ?string => $record['msisdn'] ?? null)
                ->placeholder('—'),
            TextColumn::make('gross_amount')
                ->label('Gross')
                ->alignEnd()
                ->formatStateUsing(fn ($state): string => Format::money($state)),
            TextColumn::make('rate')
                ->alignEnd()
                ->formatStateUsing(fn ($state): string => ExciseDutyReport::percent((float) $state)),
            TextColumn::make('excise_amount')
                ->label('Excise')
                ->alignEnd()
                ->weight('bold')
                ->formatStateUsing(fn ($state): string => Format::money($state)),
            TextColumn::make('net_amount')
                ->label('Net')
                ->alignEnd()
                ->formatStateUsing(fn ($state): string => Format::money($state)),
            TextColumn::make('status')
                ->badge()
                ->color(fn (?string $state): string => match ($state) {
                    'remitted' => 'success',
                    'reversed' => 'gray',
                    default => 'warning',
                })
                ->formatStateUsing(fn (?string $state): string => self::STATUSES[$state] ?? ucfirst((string) $state)),
            TextColumn::make('kra_reference')
                ->label('KRA Reference')
                ->color('gray')
                ->placeholder('—'),
        ];
    }

    protected function tableFilterDefinitions(): array
    {
        return [
            SelectFilter::make('status')->options(self::STATUSES),
            Filter::make('customer')
                ->schema([TextInput::make('customer_id')->label('Customer ID')->numeric()])
                ->indicateUsing(fn (array $data): ?string => filled($data['customer_id'] ?? null) ? 'Customer #'.$data['customer_id'] : null),
        ];
    }

    protected function emptyHeading(): string
    {
        return 'No excise duty charged';
    }
}
