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

class PurchasesPage extends Page implements HasTable
{
    use InteractsWithTable;
    use SuperAdminAccess;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Purchases';

    protected static string|null|\UnitEnum $navigationGroup = 'Financial';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.purchases-page';

    public bool $apiError = false;

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int $page, int $recordsPerPage): LengthAwarePaginator {
                try {
                    // BUG: No global purchases listing endpoint exists on the wallet API.
                    // Purchases are available per-customer via getCustomerPurchases().
                    $raw = Cache::remember('purchases_page', 300, fn () => app(GameApiService::class)->listPurchases());
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
                TextColumn::make('name')
                    ->label('Player Name')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color('info'),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => 'KES '.number_format((float) ($state ?? 0), 2)),
                TextColumn::make('value')
                    ->label('Value'),
                TextColumn::make('date')
                    ->label('Date'),
            ])
            ->emptyStateHeading('No purchases found')
            ->emptyStateDescription($this->apiError ? 'Could not load purchase data from the API.' : 'No purchases have been recorded yet.')
            ->striped();
    }
}
