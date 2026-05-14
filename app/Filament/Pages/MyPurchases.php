<?php

namespace App\Filament\Pages;

use App\Services\GameApiService;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class MyPurchases extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'My Purchases';

    protected static string|null|\UnitEnum $navigationGroup = 'Players';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.my-purchases';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['super-admin', 'agent']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int $page, int $recordsPerPage): LengthAwarePaginator {
                $user = auth()->user();
                $data = collect();

                try {
                    $raw = Cache::remember(
                        'agent_purchases_'.$user->id,
                        300,
                        fn () => app(GameApiService::class)->getPurchasesByReferral($user->referral_codes_array)
                    );
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
                TextColumn::make('player_name')
                    ->label('Player Name')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Purchase Type')
                    ->badge()
                    ->color('info'),
                TextColumn::make('amount')
                    ->label('Amount (KES)')
                    ->formatStateUsing(fn ($state) => 'KES '.number_format((float) ($state ?? 0), 2)),
                TextColumn::make('date')
                    ->label('Date'),
            ])
            ->emptyStateHeading('No purchases found')
            ->emptyStateDescription('Purchases via your referral codes will appear here.')
            ->striped();
    }
}
