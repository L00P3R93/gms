<?php

namespace App\Filament\Resources\JackpotResults\Pages;

use App\Filament\Resources\JackpotResults\JackpotResultResource;
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

class ListJackpotResults extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = JackpotResultResource::class;

    protected string $view = 'filament.resources.jackpot-results.pages.list-jackpot-results';

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
                    ->label('Tier')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ((int) $state) {
                        21 => 'Gold (21)',
                        17 => 'Silver (17)',
                        13 => 'Bronze (13)',
                        default => (string) ($state ?? '—'),
                    })
                    ->color(fn ($state): string => match ((int) $state) {
                        21 => 'warning',
                        17 => 'gray',
                        13 => 'danger',
                        default => 'gray',
                    }),
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
            ->emptyStateIcon('heroicon-o-star')
            ->emptyStateHeading(fn (): string => $this->apiError ? 'Jackpot results unavailable' : 'No jackpot results found')
            ->emptyStateDescription(fn (): string => $this->apiError
                ? 'The wallet API could not be reached. Refresh the page to try again.'
                : 'No jackpots have been completed yet.')
            ->striped();
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function fetchRecords(): array
    {
        try {
            // gameType 2 = Jackpot
            $records = Cache::remember('api_jackpot_results', 120, fn (): array => app(GameApiService::class)->getCompetitionResults(2));
            $this->apiError = false;

            return $records;
        } catch (\Throwable) {
            $this->apiError = true;

            return [];
        }
    }
}
