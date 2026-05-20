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

class AccessPage extends Page implements HasTable
{
    use InteractsWithTable;
    use SuperAdminAccess;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'Player Access';

    protected static string|UnitEnum|null $navigationGroup = 'Players';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.access-page';

    public bool $apiError = false;

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (int|string $page, int|string $recordsPerPage, ?string $search, ?string $sortColumn, ?string $sortDirection): LengthAwarePaginator => ApiTablePaginator::make(
                response: $this->fetchRecords(),
                page: $page,
                perPage: $recordsPerPage,
                search: $search,
                searchKeys: ['name', 'email', 'phone_no'],
                sortColumn: $sortColumn ?? 'id',
                sortDirection: $sortDirection ?? 'desc',
            ))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone_no')
                    ->label('Phone')
                    ->formatStateUsing(fn ($state): string => Format::maskedPhone($state))
                    ->copyable()
                    ->copyableState(fn ($state): string => (string) $state)
                    ->copyMessage('Phone copied')
                    ->searchable(),
                TextColumn::make('email')
                    ->copyable()
                    ->searchable(),
            ])
            ->emptyStateIcon('heroicon-o-key')
            ->emptyStateHeading(fn (): string => $this->apiError ? 'Customers unavailable' : 'No customers found')
            ->emptyStateDescription(fn (): string => $this->apiError
                ? 'The wallet API could not be reached. Refresh the page to try again.'
                : 'No customers have registered yet.')
            ->striped();
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function fetchRecords(): array
    {
        try {
            $records = Cache::remember('customers_list', 300, fn (): array => app(GameApiService::class)->listCustomers());
            $this->apiError = false;

            return $records;
        } catch (\Throwable) {
            $this->apiError = true;

            return [];
        }
    }
}
