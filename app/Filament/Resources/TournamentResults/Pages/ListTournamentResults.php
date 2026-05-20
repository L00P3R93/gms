<?php

namespace App\Filament\Resources\TournamentResults\Pages;

use App\Filament\Resources\TournamentResults\TournamentResultResource;
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

class ListTournamentResults extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = TournamentResultResource::class;

    protected string $view = 'filament.resources.tournament-results.pages.list-tournament-results';

    public bool $apiError = false;

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (int|string $page, int|string $recordsPerPage, ?string $search, ?string $sortColumn, ?string $sortDirection): LengthAwarePaginator => ApiTablePaginator::make(
                response: $this->fetchRecords(),
                page: $page,
                perPage: $recordsPerPage,
                search: $search,
                searchKeys: ['competition_id', 'name'],
                sortColumn: $sortColumn,
                sortDirection: $sortDirection,
            ))
            ->columns([
                TextColumn::make('competition_id')
                    ->label('Competition ID')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Player/Winner')
                    ->searchable(),
                TextColumn::make('jp_rounds')
                    ->label('Rounds'),
                TextColumn::make('level')
                    ->label('Level')
                    ->badge()
                    ->color('info'),
                TextColumn::make('payment_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ucfirst((string) ($state ?? '—')))
                    ->color(fn ($state): string => match ($state) {
                        'win' => 'success',
                        'deposit' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => Format::money($state)),
                TextColumn::make('income')
                    ->label('House Income')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => Format::money($state)),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-trophy')
            ->emptyStateHeading(fn (): string => $this->apiError ? 'Tournament results unavailable' : 'No tournament results found')
            ->emptyStateDescription(fn (): string => $this->apiError
                ? 'The wallet API could not be reached. Refresh the page to try again.'
                : 'No tournaments have been completed yet.')
            ->striped();
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function fetchRecords(): array
    {
        try {
            // gameType 1 = Tournament
            $records = Cache::remember('api_tournament_results', 120, fn (): array => app(GameApiService::class)->getCompetitionResults(1));
            $this->apiError = false;

            return $records;
        } catch (\Throwable) {
            $this->apiError = true;

            return [];
        }
    }
}
