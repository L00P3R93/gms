<?php

namespace App\Filament\Pages;

use App\Models\Account;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class MyCustomers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'My Customers';

    protected static string|null|\UnitEnum $navigationGroup = 'Players';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.my-customers';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['super-admin', 'agent']) ?? false;
    }

    public function table(Table $table): Table
    {
        $codes = auth()->user()?->referral_codes_array ?? [];

        return $table
            ->query(
                Account::query()
                    ->select(['id', 'name', 'phone', 'email', 'ref_code'])
                    ->when(
                        ! auth()->user()?->hasRole('super-admin') && $codes,
                        fn ($q) => $q->whereIn('ref_code', $codes)
                    )
            )
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Phone')
                    ->formatStateUsing(fn ($state) => '****'.substr($state, -4)),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('ref_code')
                    ->label('Referral Code')
                    ->badge()
                    ->color('info'),
            ])
            ->striped()
            ->defaultSort('id', 'desc');
    }
}
