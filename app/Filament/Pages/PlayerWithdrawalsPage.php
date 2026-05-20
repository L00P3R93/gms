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

class PlayerWithdrawalsPage extends Page implements HasTable
{
    use InteractsWithTable;
    use SuperAdminAccess;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Player Withdrawals';

    protected static string|UnitEnum|null $navigationGroup = 'Financial';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.player-withdrawals-page';

    public bool $apiError = false;

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (int|string $page, int|string $recordsPerPage, ?string $search, ?string $sortColumn, ?string $sortDirection): LengthAwarePaginator => ApiTablePaginator::make(
                response: $this->fetchRecords(),
                page: $page,
                perPage: $recordsPerPage,
                search: $search,
                searchKeys: ['name', 'phone', 'transaction_id'],
                sortColumn: $sortColumn,
                sortDirection: $sortDirection,
            ))
            ->columns([
                TextColumn::make('transaction_id')
                    ->label('Transaction ID')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('name')
                    ->label('Player Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => Format::money($state)),
                TextColumn::make('date')
                    ->label('Date')
                    ->sortable(),
            ])
            ->emptyStateIcon('heroicon-o-arrow-up-tray')
            ->emptyStateHeading(fn (): string => $this->apiError ? 'Withdrawals unavailable' : 'No withdrawals found')
            ->emptyStateDescription(fn (): string => $this->apiError
                ? 'The wallet API has no global withdrawals endpoint, or could not be reached. See TODO.md.'
                : 'No withdrawals have been recorded yet.')
            ->striped();
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function fetchRecords(): array
    {
        try {
            // TODO: GameApi exposes no global /withdrawals listing endpoint (returns 404).
            // Withdrawals are only available per-customer via getCustomerTransactions(). See TODO.md.
            $records = Cache::remember('player_withdrawals_page', 300, fn (): array => app(GameApiService::class)->listWithdrawals());
            $this->apiError = false;

            return $records;
        } catch (\Throwable) {
            $this->apiError = true;

            return [];
        }
    }
}
