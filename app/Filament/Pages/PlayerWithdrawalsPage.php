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

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int $page, int $recordsPerPage): LengthAwarePaginator {
                try {
                    $raw = Cache::remember('player_withdrawals_page', 300, fn () => app(GameApiService::class)->getWithdrawals());
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
                TextColumn::make('transaction_id')
                    ->label('Transaction ID')
                    ->copyable(),
                TextColumn::make('name')
                    ->label('Player Name')
                    ->searchable(),
                TextColumn::make('phone'),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => 'KES '.number_format((float) ($state ?? 0), 2)),
                TextColumn::make('date')
                    ->label('Date'),
            ])
            ->emptyStateHeading('No withdrawals found')
            ->emptyStateDescription('Player withdrawal data is fetched from the game API.')
            ->striped();
    }
}
