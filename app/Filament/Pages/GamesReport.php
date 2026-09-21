<?php

namespace App\Filament\Pages;

use App\Support\Format;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;

class GamesReport extends FinanceListReportPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationLabel = 'Games Report';

    protected static ?int $navigationSort = 21;

    protected ?string $heading = 'Single Games';

    protected function reportKey(): string
    {
        return 'games';
    }

    protected function exportKey(): ?string
    {
        return 'games';
    }

    protected function extraFilters(): array
    {
        return [
            'outcome' => $this->filterValue('outcome'),
            'players' => $this->tableFilters['players']['players'] ?? null,
        ];
    }

    protected function summaryStats(array $data): array
    {
        $summary = $data['summary'] ?? [];
        $stakes = (float) ($summary['stakes'] ?? 0);
        $take = (float) ($summary['house_take'] ?? 0);

        return [
            ['label' => 'Games', 'value' => number_format((int) ($summary['games'] ?? 0)), 'icon' => 'heroicon-m-puzzle-piece', 'color' => 'primary'],
            ['label' => 'Total Stakes', 'value' => Format::money($stakes), 'description' => 'Paid to players: '.Format::money($summary['paid_to_players'] ?? 0), 'icon' => 'heroicon-m-banknotes', 'color' => 'info'],
            ['label' => 'House Take', 'value' => Format::money($take), 'description' => $stakes > 0 ? number_format($take / $stakes * 100, 1).'% of stakes' : null, 'icon' => 'heroicon-m-home', 'color' => 'success'],
            ['label' => 'Refunded', 'value' => Format::money($summary['refunded'] ?? 0), 'icon' => 'heroicon-m-arrow-uturn-left', 'color' => 'warning'],
        ];
    }

    protected function blocks(array $data): array
    {
        $summary = $data['summary'] ?? [];
        $row = fn (string $label, array $stats): array => [
            $label,
            number_format((int) ($stats['games'] ?? 0)),
            Format::money($stats['stakes'] ?? 0),
            Format::money($stats['paid_to_players'] ?? 0),
            Format::money($stats['refunded'] ?? 0),
            Format::money($stats['house_take'] ?? 0),
        ];
        $headers = ['', 'Games', 'Stakes', 'Paid to players', 'Refunded', 'House take'];

        return [
            [
                'title' => 'By outcome',
                'headers' => ['Outcome', ...array_slice($headers, 1)],
                'rows' => collect($summary['by_outcome'] ?? [])
                    ->map(fn (array $stats, string $outcome): array => $row(str($outcome)->replace('_', ' ')->title()->toString(), $stats))
                    ->values()
                    ->all(),
            ],
            [
                'title' => 'By number of players',
                'headers' => ['Players', ...array_slice($headers, 1)],
                'rows' => collect($summary['by_players'] ?? [])
                    ->map(fn (array $stats, $players): array => $row($players.' players', $stats))
                    ->values()
                    ->all(),
            ],
        ];
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('created_at')
                ->label('Started')
                ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
            TextColumn::make('game_id')
                ->label('Game')
                ->copyable(),
            TextColumn::make('outcome')
                ->badge()
                ->color(fn (?string $state): string => match ($state) {
                    'completed' => 'success',
                    'open' => 'warning',
                    'dropped', 'refunded' => 'danger',
                    default => 'gray',
                })
                ->formatStateUsing(fn (?string $state): string => str((string) $state)->replace('_', ' ')->title()->toString()),
            TextColumn::make('players')
                ->alignCenter(),
            TextColumn::make('stakes')
                ->alignEnd()
                ->formatStateUsing(fn ($state): string => Format::money($state)),
            TextColumn::make('paid_to_players')
                ->label('Paid Out')
                ->alignEnd()
                ->formatStateUsing(fn ($state): string => Format::money($state)),
            TextColumn::make('house_take')
                ->label('House Take')
                ->alignEnd()
                ->weight('bold')
                ->placeholder('—')
                ->formatStateUsing(fn ($state): string => Format::money($state)),
            TextColumn::make('escrow_balance')
                ->label('Held')
                ->alignEnd()
                ->color('gray')
                ->formatStateUsing(fn ($state): string => Format::money($state)),
        ];
    }

    protected function tableFilterDefinitions(): array
    {
        return [
            SelectFilter::make('outcome')->options(collect(['open', 'completed', 'dropped', 'refunded', 'closed_empty'])
                ->mapWithKeys(fn (string $key): array => [$key => str($key)->replace('_', ' ')->title()->toString()])
                ->all()),
            Filter::make('players')
                ->schema([TextInput::make('players')->label('Players in game')->numeric()->minValue(2)])
                ->indicateUsing(fn (array $data): ?string => filled($data['players'] ?? null) ? $data['players'].' players' : null),
        ];
    }

    protected function emptyHeading(): string
    {
        return 'No games found';
    }
}
