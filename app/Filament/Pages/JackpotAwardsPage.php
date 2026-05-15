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

class JackpotAwardsPage extends Page implements HasTable
{
    use InteractsWithTable;
    use SuperAdminAccess;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Jackpot Awards';

    protected static string|UnitEnum|null $navigationGroup = 'Game Results';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.awards-page';

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
                    $data = Cache::remember('api_jackpot_awards', 300, fn () => collect(
                        app(GameApiService::class)->getCompetitionAwards(2)
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
                    ->label('Winner'),
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
                TextColumn::make('amount')
                    ->label('Prize Amount')
                    ->formatStateUsing(fn ($state) => 'KES '.number_format((float) ($state ?? 0), 2)),
                TextColumn::make('income')
                    ->label('House Income')
                    ->formatStateUsing(fn ($state) => 'KES '.number_format((float) ($state ?? 0), 2)),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime(),
            ])
            ->emptyStateHeading('No jackpot awards found')
            ->emptyStateDescription($this->apiError ? 'Could not load data from the API.' : 'No jackpot awards have been recorded yet.')
            ->striped()
            ->defaultSort('created_at', 'desc');
    }
}
