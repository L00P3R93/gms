<?php

namespace App\Filament\Resources\TournamentResults\Pages;

use App\Filament\Resources\TournamentResults\TournamentResultResource;
use App\Services\GameApiService;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ListTournamentResults extends ListRecords
{
    protected static string $resource = TournamentResultResource::class;

    protected string $view = 'filament.resources.tournament-results.pages.list-tournament-results';

    public bool $apiError = false;

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int $page, int $recordsPerPage): LengthAwarePaginator {
                try {
                    // gameType 1 = Tournament
                    $data = Cache::remember('api_tournament_results', 120, fn () => collect(
                        app(GameApiService::class)->getCompetitionResults(1)
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
                TextColumn::make('competition_id')
                    ->label('Competition ID')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Player/Winner'),
                TextColumn::make('jp_rounds')
                    ->label('Rounds'),
                TextColumn::make('level')
                    ->label('Level')
                    ->badge()
                    ->color('info'),
                TextColumn::make('payment_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst((string) ($state ?? '—')))
                    ->color(fn ($state) => match ($state) {
                        'win' => 'success',
                        'deposit' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => 'KES '.number_format((float) ($state ?? 0), 2)),
                TextColumn::make('income')
                    ->label('House Income')
                    ->formatStateUsing(fn ($state) => 'KES '.number_format((float) ($state ?? 0), 2)),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime(),
            ])
            ->emptyStateHeading('No tournament results found')
            ->emptyStateDescription($this->apiError ? 'Could not load results from the API.' : 'No tournaments have been completed yet.')
            ->striped();
    }
}
