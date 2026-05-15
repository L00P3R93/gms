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

class DepositsPage extends Page implements HasTable
{
    use InteractsWithTable;
    use SuperAdminAccess;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationLabel = 'Deposits';

    protected static string|null|\UnitEnum $navigationGroup = 'Financial';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.deposits-page';

    public bool $apiError = false;

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int $page, int $recordsPerPage): LengthAwarePaginator {
                try {
                    $raw = Cache::remember('deposits_page', 300, fn () => app(GameApiService::class)->listDeposits());
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
            ->emptyStateHeading('No deposits found')
            ->emptyStateDescription($this->apiError ? 'Could not load deposit data from the API.' : 'No deposits have been recorded yet.')
            ->striped();
    }
}
