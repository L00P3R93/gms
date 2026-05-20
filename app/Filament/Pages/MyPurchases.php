<?php

namespace App\Filament\Pages;

use App\Services\GameApiService;
use App\Support\ApiTablePaginator;
use App\Support\Format;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class MyPurchases extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'My Purchases';

    protected static string|UnitEnum|null $navigationGroup = 'Players';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.my-purchases';

    protected static bool $shouldRegisterNavigation = false;

    public bool $apiError = false;

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (int|string $page, int|string $recordsPerPage, ?string $search, ?string $sortColumn, ?string $sortDirection): LengthAwarePaginator => ApiTablePaginator::make(
                response: $this->fetchRecords(),
                page: $page,
                perPage: $recordsPerPage,
                search: $search,
                searchKeys: ['player_name', 'type'],
                sortColumn: $sortColumn,
                sortDirection: $sortDirection,
            ))
            ->columns([
                TextColumn::make('player_name')
                    ->label('Player Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Purchase Type')
                    ->badge()
                    ->color('info')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => Format::money($state)),
                TextColumn::make('date')
                    ->label('Date')
                    ->sortable(),
            ])
            ->emptyStateIcon('heroicon-o-shopping-cart')
            ->emptyStateHeading(fn (): string => $this->apiError ? 'Purchases unavailable' : 'No purchases found')
            ->emptyStateDescription(fn (): string => $this->apiError
                ? 'The wallet API could not be reached. Refresh the page to try again.'
                : 'Purchases made via your referral codes will appear here.')
            ->striped();
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function fetchRecords(): array
    {
        $user = auth()->user();

        try {
            $records = Cache::remember(
                'agent_purchases_'.$user->id,
                300,
                fn (): array => app(GameApiService::class)->getPurchasesByReferral($user->referral_codes_array),
            );
            $this->apiError = false;

            return $records;
        } catch (\Throwable) {
            $this->apiError = true;

            return [];
        }
    }
}
