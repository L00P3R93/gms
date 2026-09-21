<?php

namespace App\Filament\Pages;

use App\Support\Format;
use BackedEnum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class CompetitionsReport extends FinanceListReportPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Competitions Report';

    protected static ?int $navigationSort = 22;

    protected ?string $heading = 'Tournaments & Jackpots';

    protected function reportKey(): string
    {
        return 'competitions';
    }

    protected function exportKey(): ?string
    {
        return 'competitions';
    }

    protected function extraFilters(): array
    {
        return ['game_type' => $this->filterValue('game_type')];
    }

    protected function summaryStats(array $data): array
    {
        $summary = $data['summary'] ?? [];

        return [
            ['label' => 'Competitions', 'value' => number_format((int) ($summary['competitions'] ?? 0)), 'description' => number_format((int) ($summary['players'] ?? 0)).' players', 'icon' => 'heroicon-m-trophy', 'color' => 'primary'],
            ['label' => 'Entries', 'value' => Format::money($summary['entries'] ?? 0), 'icon' => 'heroicon-m-ticket', 'color' => 'info'],
            ['label' => 'House Cut', 'value' => Format::money($summary['house_cut'] ?? 0), 'icon' => 'heroicon-m-home', 'color' => 'success'],
            ['label' => 'Prizes Paid', 'value' => Format::money($summary['prizes_paid'] ?? 0), 'description' => 'Outstanding: '.Format::money($summary['outstanding'] ?? 0), 'icon' => 'heroicon-m-gift', 'color' => 'warning'],
        ];
    }

    protected function blocks(array $data): array
    {
        return [
            [
                'title' => 'By type and rounds',
                'headers' => ['Type', 'Rounds', 'Competitions', 'Players', 'Entries', 'House cut', 'Prizes paid', 'Outstanding'],
                'rows' => collect($data['summary']['by_type_and_rounds'] ?? [])
                    ->map(fn (array $row): array => [
                        ucfirst((string) ($row['type'] ?? '—')),
                        (string) ($row['rounds'] ?? '—'),
                        number_format((int) ($row['competitions'] ?? 0)),
                        number_format((int) ($row['players'] ?? 0)),
                        Format::money($row['entries'] ?? 0),
                        Format::money($row['house_cut'] ?? 0),
                        Format::money($row['prizes_paid'] ?? 0),
                        Format::money($row['outstanding'] ?? 0),
                    ])
                    ->all(),
            ],
        ];
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('started_at')
                ->label('Started')
                ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
            TextColumn::make('cmp_uid')
                ->label('Competition')
                ->copyable(),
            TextColumn::make('type')
                ->badge()
                ->color(fn (?string $state): string => $state === 'jackpot' ? 'warning' : 'info')
                ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state)),
            TextColumn::make('rounds')
                ->alignCenter(),
            TextColumn::make('players')
                ->alignCenter(),
            TextColumn::make('entries')
                ->alignEnd()
                ->formatStateUsing(fn ($state): string => Format::money($state)),
            TextColumn::make('house_cut')
                ->label('House Cut')
                ->alignEnd()
                ->weight('bold')
                ->formatStateUsing(fn ($state): string => Format::money($state)),
            TextColumn::make('prizes_paid')
                ->label('Prizes Paid')
                ->alignEnd()
                ->formatStateUsing(fn ($state): string => Format::money($state)),
            TextColumn::make('outstanding')
                ->alignEnd()
                ->color(fn ($state): string => (float) $state > 0 ? 'warning' : 'gray')
                ->formatStateUsing(fn ($state): string => Format::money($state)),
            TextColumn::make('unaccounted')
                ->alignEnd()
                ->color(fn ($state): string => (float) $state != 0.0 ? 'danger' : 'gray')
                ->formatStateUsing(fn ($state): string => Format::money($state))
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    protected function tableFilterDefinitions(): array
    {
        return [
            SelectFilter::make('game_type')->label('Type')->options(['1' => 'Tournament', '2' => 'Jackpot']),
        ];
    }

    protected function emptyHeading(): string
    {
        return 'No competitions found';
    }
}
