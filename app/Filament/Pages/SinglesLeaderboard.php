<?php

namespace App\Filament\Pages;

use App\Services\GameApiService;
use App\Traits\SuperAdminAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class SinglesLeaderboard extends Page implements HasTable
{
    use InteractsWithTable;
    use SuperAdminAccess;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Singles Leaderboard';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.singles-leaderboard';

    public bool $apiError = false;

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int $page, int $recordsPerPage): LengthAwarePaginator {
                try {
                    $response = Cache::remember('api_singles_leaderboard', 300, fn () => app(GameApiService::class)->getLeaderboard(
                        now()->startOfMonth()->toDateString(),
                        now()->toDateString(),
                    ));

                    $data = collect($response['single_leaderboard'] ?? $response['data'] ?? [])
                        ->values()
                        ->toArray();

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
                TextColumn::make('name')
                    ->label('Player Name'),
                TextColumn::make('phone')
                    ->label('Phone')
                    ->formatStateUsing(fn ($state) => $state ? '****'.substr((string) $state, -4) : '—'),
                TextColumn::make('wins')
                    ->label('Wins')
                    ->formatStateUsing(fn ($state) => number_format((int) ($state ?? 0))),
                TextColumn::make('total_winnings')
                    ->label('Total Winnings')
                    ->formatStateUsing(fn ($state) => 'KES '.number_format((float) ($state ?? 0), 2)),
            ])
            ->emptyStateHeading('No leaderboard data')
            ->emptyStateDescription($this->apiError ? 'Could not load leaderboard from the API.' : 'No singles game data for this period.')
            ->striped();
    }
}
