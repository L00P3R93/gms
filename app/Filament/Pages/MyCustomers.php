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

class MyCustomers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'My Customers';

    protected static string|UnitEnum|null $navigationGroup = 'Players';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.my-customers';

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
                    ->searchable()
                    ->formatStateUsing(fn ($state): string => Format::maskedPhone($state)),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('referral_code')
                    ->label('Referral Code')
                    ->badge()
                    ->color('info'),
            ])
            ->emptyStateIcon('heroicon-o-user-circle')
            ->emptyStateHeading(fn (): string => $this->apiError ? 'Customers unavailable' : 'No customers found')
            ->emptyStateDescription(fn (): string => $this->apiError
                ? 'The wallet API could not be reached. Refresh the page to try again.'
                : 'Customers who registered with your referral codes will appear here.')
            ->striped();
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function fetchRecords(): array
    {
        $user = auth()->user();
        $codes = $user?->referral_codes_array ?? [];
        $isSuperAdmin = $user?->hasRole('super-admin') ?? false;

        try {
            if ($isSuperAdmin) {
                $records = Cache::remember('customers_list', 300, fn (): array => app(GameApiService::class)->listCustomers());
            } elseif ($codes !== []) {
                $records = app(GameApiService::class)->getCustomersByReferral($codes);
            } else {
                $records = [];
            }

            $this->apiError = false;

            return $records;
        } catch (\Throwable) {
            $this->apiError = true;

            return [];
        }
    }
}
