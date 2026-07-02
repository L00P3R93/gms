<?php

namespace App\Livewire;

use App\Services\GameApiService;
use App\Support\ApiTablePaginator;
use App\Support\Format;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\View\View;

class JackpotGamesTable extends BaseWidget
{
    public int $customerId;

    public ?string $search = null;

    public ?string $sortColumn = null;

    public ?string $sortDirection = null;

    public int $page = 1;

    public int $perPage = 10;

    protected int|string|array $columnSpan = 'full';

    public function mount(int $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function gotoPage($page, $pageName = 'page'): void
    {
        $this->page = max(1, (int) $page);
    }

    public function nextPage($pageName = 'page'): void
    {
        $this->page++;
    }

    public function previousPage($pageName = 'page'): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function changePerPage($perPage): void
    {
        $this->perPage = (int) $perPage;
        $this->page = 1;
    }

    public function table(Table $table): Table
    {
        $gameApi = app(GameApiService::class);

        try {
            $response = $gameApi->getCustomerGamesPlayed(
                $this->customerId,
                jackpotPage: $this->page,
                perPage: $this->perPage
            );
        } catch (\Throwable $e) {
            $response = ['jackpot_games' => ['data' => []]];
        }

        $gamesData = $response['jackpot_games'] ?? [];

        $paginator = ApiTablePaginator::make(
            $gamesData,
            page: $this->page,
            perPage: $this->perPage,
            search: $this->search,
            searchKeys: ['competition_id'],
            sortColumn: $this->sortColumn,
            sortDirection: $this->sortDirection,
        );

        return $table
            ->records(fn () => $paginator)
            ->columns([
                TextColumn::make('competition_id')
                    ->label('Competition ID'),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state): string => Format::money($state)),
                TextColumn::make('level')
                    ->label('Level')
                    ->badge(),
                TextColumn::make('payment_type')
                    ->label('Result')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ucfirst((string) ($state ?? '—')))
                    ->color(fn ($state): string => match ($state) {
                        'win' => 'success',
                        'deposit' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50]);
    }

    public function getViewData(): array
    {
        return [
            'customerId' => $this->customerId,
        ];
    }

    public function render(): View
    {
        return view('livewire.jackpot-games-table', $this->getViewData());
    }
}
