<?php

namespace App\Filament\Pages;

use App\Services\GameApiService;
use App\Traits\SuperAdminAccess;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class PlayerWithdrawalsPage extends Page implements HasTable
{
    use InteractsWithTable;
    use SuperAdminAccess;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Player Withdrawals';

    protected static string|null|\UnitEnum $navigationGroup = 'Financial';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.player-withdrawals-page';

    public bool $apiError = false;

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int $page, int $recordsPerPage): LengthAwarePaginator {
                try {
                    // BUG: No global withdrawal listing endpoint exists on the wallet API.
                    // Withdrawals are available per-customer via getCustomerTransactions().
                    $raw = Cache::remember('player_withdrawals_page', 300, fn () => app(GameApiService::class)->listWithdrawals());
                    $items = $raw['data'] ?? (is_array($raw) && ! isset($raw['status']) ? $raw : []);
                    $data = collect($items)->filter(fn ($item) => is_array($item))->values();

                    $this->apiError = false;
                } catch (\Throwable) {
                    $this->apiError = true;
                    $data = collect();
                }

                return new LengthAwarePaginator(
                    $data->forPage($page, $recordsPerPage)->values()->toArray(),
                    $data->count(),
                    $recordsPerPage,
                    $page,
                );
            })
            ->columns([
                TextColumn::make('transaction_id')
                    ->label('Transaction ID')
                    ->copyable(),
                TextColumn::make('name')
                    ->label('Player Name')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Phone'),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => 'KES '.number_format((float) ($state ?? 0), 2)),
                TextColumn::make('date')
                    ->label('Date'),
            ])
            ->emptyStateHeading('No withdrawals found')
            ->emptyStateDescription($this->apiError ? 'Could not load withdrawal data from the API.' : 'No withdrawals have been recorded yet.')
            ->striped();
    }
}
