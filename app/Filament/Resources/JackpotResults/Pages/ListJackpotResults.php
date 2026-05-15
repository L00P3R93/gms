<?php

namespace App\Filament\Resources\JackpotResults\Pages;

use App\Filament\Resources\JackpotResults\JackpotResultResource;
use App\Services\GameApiService;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ListJackpotResults extends ListRecords
{
    protected static string $resource = JackpotResultResource::class;

    protected string $view = 'filament.resources.jackpot-results.pages.list-jackpot-results';

    public bool $apiError = false;

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int $page, int $recordsPerPage): LengthAwarePaginator {
                try {
                    // gameType 2 = Jackpot
                    $data = Cache::remember('api_jackpot_results', 120, fn () => collect(
                        app(GameApiService::class)->getCompetitionResults(2)
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
                    ->label('Tier')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ((int) $state) {
                        21 => 'Gold (21)',
                        17 => 'Silver (17)',
                        13 => 'Bronze (13)',
                        default => (string) ($state ?? '—'),
                    })
                    ->color(fn ($state) => match ((int) $state) {
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
            ->emptyStateHeading('No jackpot results found')
            ->emptyStateDescription($this->apiError ? 'Could not load results from the API.' : 'No jackpots have been completed yet.')
            ->striped();
    }
}
