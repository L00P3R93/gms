<?php

namespace App\Filament\Resources\GameResults\Pages;

use App\Filament\Resources\GameResults\GameResultResource;
use App\Filament\Resources\GameResults\Widgets\GameResultStatsWidget;
use App\Services\GameApiService;
use App\Support\ApiTablePaginator;
use App\Support\Format;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ListGameResults extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = GameResultResource::class;

    protected string $view = 'filament.resources.game-results.pages.list-game-results';

    public bool $apiError = false;

    /**
     * @return array<int, class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            GameResultStatsWidget::class,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (int|string $page, int|string $recordsPerPage, ?string $search, ?string $sortColumn, ?string $sortDirection): LengthAwarePaginator => ApiTablePaginator::make(
                response: $this->fetchRecords(),
                page: $page,
                perPage: $recordsPerPage,
                search: $search,
                searchKeys: ['game_id', 'name'],
                sortColumn: $sortColumn,
                sortDirection: $sortDirection,
            ))
            ->columns([
                TextColumn::make('game_id')
                    ->label('Game ID')
                    ->searchable(),
                TextColumn::make('players')
                    ->label('Players')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state.' Players')
                    ->color(fn ($state): string => match ((int) $state) {
                        2 => 'info',
                        3 => 'warning',
                        4 => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('total_bet')
                    ->label('Total Bet')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => Format::money($state)),
                TextColumn::make('name')
                    ->label('Winner')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Winnings (90%)')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => Format::money($state)),
                TextColumn::make('income')
                    ->label('House Income (10%)')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => Format::money($state)),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-puzzle-piece')
            ->emptyStateHeading(fn (): string => $this->apiError ? 'Game results unavailable' : 'No game results found')
            ->emptyStateDescription(fn (): string => $this->apiError
                ? 'The wallet API could not be reached. Refresh the page to try again.'
                : 'No single games have been completed yet.')
            ->striped();
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function fetchRecords(): array
    {
        try {
            $records = Cache::remember('api_game_results', 120, fn (): array => app(GameApiService::class)->getGameResults());
            $this->apiError = false;

            return $records;
        } catch (\Throwable) {
            $this->apiError = true;

            return [];
        }
    }
}
