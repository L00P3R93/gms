<?php

namespace App\Filament\Resources\GameResults\Pages;

use App\Filament\Resources\GameResults\GameResultResource;
use App\Filament\Resources\GameResults\Widgets\GameResultStatsWidget;
use App\Services\GameApiService;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ListGameResults extends ListRecords
{
    protected static string $resource = GameResultResource::class;

    protected string $view = 'filament.resources.game-results.pages.list-game-results';

    public bool $apiError = false;

    protected function getHeaderWidgets(): array
    {
        return [
            GameResultStatsWidget::class,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int $page, int $recordsPerPage): LengthAwarePaginator {
                try {
                    $data = Cache::remember('api_game_results', 120, fn () => collect(
                        app(GameApiService::class)->getGameResults()
                    )->values()->toArray());

                    $this->apiError = false;
                } catch (\Throwable) {
                    $this->apiError = true;
                    $data = [];
                }

                $collection = collect($data);

                return new LengthAwarePaginator(
                    $collection->forPage($page, $recordsPerPage)->values()->toArray(),
                    $collection->count(),
                    $recordsPerPage,
                    $page,
                );
            })
            ->columns([
                TextColumn::make('game_id')
                    ->label('Game ID')
                    ->searchable(),
                TextColumn::make('players')
                    ->label('Players')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state.' Players')
                    ->color(fn ($state) => match ((int) $state) {
                        2 => 'info',
                        3 => 'warning',
                        4 => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('total_bet')
                    ->label('Total Bet')
                    ->formatStateUsing(fn ($state) => 'KES '.number_format((float) ($state ?? 0), 2)),
                TextColumn::make('name')
                    ->label('Winner'),
                TextColumn::make('amount')
                    ->label('Winnings (90%)')
                    ->formatStateUsing(fn ($state) => 'KES '.number_format((float) ($state ?? 0), 2)),
                TextColumn::make('income')
                    ->label('House Income (10%)')
                    ->formatStateUsing(fn ($state) => 'KES '.number_format((float) ($state ?? 0), 2)),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime(),
            ])
            ->emptyStateHeading('No game results found')
            ->emptyStateDescription($this->apiError ? 'Could not load results from the API.' : 'No single games have been completed yet.')
            ->striped();
    }
}
