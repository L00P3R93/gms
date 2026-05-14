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

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int $page, int $recordsPerPage): LengthAwarePaginator {
                try {
                    $raw = Cache::remember('purchases_page', 300, fn () => app(GameApiService::class)->getPurchases());
                    $items = is_array($raw) ? $raw : [];
                    $data = collect($items)->filter(fn ($item) => is_array($item))->values();
                } catch (\Throwable) {
                    $data = collect();
                }

                $total = $data->count();
                $sliced = $data->forPage($page, $recordsPerPage);

                return new LengthAwarePaginator($sliced, $total, $recordsPerPage, $page);
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
                    ->label('Amount (KES)')
                    ->formatStateUsing(fn ($state) => 'KES '.number_format((float) ($state ?? 0), 2)),
                TextColumn::make('value')
                    ->label('Value'),
                TextColumn::make('date')
                    ->label('Date'),
            ])
            ->emptyStateHeading('No purchases found')
            ->emptyStateDescription('Purchase data is fetched from the game API.')
            ->striped();
    }
}
