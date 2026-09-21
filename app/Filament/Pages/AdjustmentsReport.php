<?php

namespace App\Filament\Pages;

use App\Support\Format;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;

class AdjustmentsReport extends FinanceListReportPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'Balance Adjustments';

    protected static ?int $navigationSort = 23;

    protected ?string $heading = 'Manual Balance Adjustments';

    protected function reportKey(): string
    {
        return 'adjustments';
    }

    protected function exportKey(): ?string
    {
        return 'adjustments';
    }

    protected function extraFilters(): array
    {
        return ['customer_id' => $this->tableFilters['customer']['customer_id'] ?? null];
    }

    protected function summaryStats(array $data): array
    {
        $summary = $data['summary'] ?? [];
        $withoutReason = (int) ($summary['without_reason'] ?? 0);

        return [
            ['label' => 'Adjustments', 'value' => number_format((int) ($summary['entries'] ?? 0)), 'icon' => 'heroicon-m-adjustments-horizontal', 'color' => 'primary'],
            ['label' => 'Credited', 'value' => Format::money($summary['credited'] ?? 0), 'icon' => 'heroicon-m-plus-circle', 'color' => 'success'],
            ['label' => 'Debited', 'value' => Format::money($summary['debited'] ?? 0), 'icon' => 'heroicon-m-minus-circle', 'color' => 'danger'],
            ['label' => 'Without a Reason', 'value' => number_format($withoutReason), 'description' => 'Net: '.Format::money($summary['net'] ?? 0), 'icon' => 'heroicon-m-exclamation-triangle', 'color' => $withoutReason > 0 ? 'warning' : 'gray'],
        ];
    }

    protected function blocks(array $data): array
    {
        $summary = $data['summary'] ?? [];

        return [
            [
                'title' => 'By reason',
                'headers' => ['Reason', 'Adjustments', 'Net'],
                'rows' => collect($summary['by_reason'] ?? [])
                    ->map(fn (array $row): array => [(string) ($row['reason'] ?? '—'), number_format((int) ($row['entries'] ?? 0)), Format::money($row['net'] ?? 0)])
                    ->all(),
            ],
            [
                'title' => 'By actor (API key)',
                'headers' => ['Actor', 'Adjustments', 'Net'],
                'rows' => collect($summary['by_actor'] ?? [])
                    ->map(fn (array $row): array => [(string) ($row['actor'] ?? '—'), number_format((int) ($row['entries'] ?? 0)), Format::money($row['net'] ?? 0)])
                    ->all(),
            ],
        ];
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('created_at')
                ->label('Time')
                ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
            TextColumn::make('customer_name')
                ->label('Player')
                ->weight('bold')
                ->description(fn (array $record): string => '#'.($record['customer_id'] ?? '—'))
                ->placeholder('—'),
            TextColumn::make('direction')
                ->badge()
                ->color(fn (?string $state): string => $state === 'credit' ? 'success' : 'danger')
                ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state)),
            TextColumn::make('amount')
                ->alignEnd()
                ->weight('bold')
                ->formatStateUsing(fn ($state): string => Format::money($state)),
            TextColumn::make('balance_after')
                ->label('Balance')
                ->alignEnd()
                ->description(fn (array $record): string => 'was '.Format::money($record['balance_before'] ?? 0))
                ->formatStateUsing(fn ($state): string => Format::money($state)),
            TextColumn::make('reason')
                ->color(fn (?string $state): string => $state === 'unspecified' ? 'warning' : 'gray')
                ->wrap(),
            TextColumn::make('actor')
                ->color('gray'),
            TextColumn::make('operation')
                ->color('gray')
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    protected function tableFilterDefinitions(): array
    {
        return [
            Filter::make('customer')
                ->schema([TextInput::make('customer_id')->label('Customer ID')->numeric()])
                ->indicateUsing(fn (array $data): ?string => filled($data['customer_id'] ?? null) ? 'Customer #'.$data['customer_id'] : null),
        ];
    }

    protected function emptyHeading(): string
    {
        return 'No adjustments in this period';
    }
}
