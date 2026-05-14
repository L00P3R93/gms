<?php

namespace App\Filament\Pages;

use App\Models\Account;
use App\Traits\SuperAdminAccess;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class AccessPage extends Page implements HasTable
{
    use InteractsWithTable;
    use SuperAdminAccess;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'Player Access';

    protected static string|null|\UnitEnum $navigationGroup = 'Players';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.access-page';

    public function table(Table $table): Table
    {
        return $table
            ->query(Account::query()->select(['id', 'name', 'phone', 'email']))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->formatStateUsing(fn ($state) => substr($state, 0, 3).'****'.substr($state, -4))
                    ->copyable()
                    ->copyMessage('Phone copied'),
                TextColumn::make('email')
                    ->copyable()
                    ->searchable(),
            ])
            ->striped()
            ->defaultSort('id', 'desc');
    }
}
