<?php

namespace App\Filament\Pages;

use App\Services\GameApiService;
use App\Support\ApiTablePaginator;
use App\Support\Format;
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
                    ->label('Winner')
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
                TextColumn::make('amount')
                    ->label('Prize Amount')
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
            ->emptyStateHeading(fn (): string => $this->apiError ? 'Jackpot awards unavailable' : 'No jackpot awards found')
            ->emptyStateDescription(fn (): string => $this->apiError
                ? 'The wallet API could not be reached. Refresh the page to try again.'
                : 'No jackpot awards have been recorded yet.')
            ->striped();
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function fetchRecords(): array
    {
        try {
            // gameType 2 = Jackpot
            $records = Cache::remember('api_jackpot_awards', 300, fn (): array => app(GameApiService::class)->getCompetitionAwards(2));
            $this->apiError = false;

            return $records;
        } catch (\Throwable) {
            $this->apiError = true;

            return [];
        }
    }
}
