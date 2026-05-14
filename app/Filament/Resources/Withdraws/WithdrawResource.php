<?php

namespace App\Filament\Resources\Withdraws;

use App\Filament\Resources\Withdraws\Pages\ListWithdraws;
use App\Filament\Resources\Withdraws\Schemas\WithdrawForm;
use App\Filament\Resources\Withdraws\Tables\WithdrawsTable;
use App\Models\Withdraw;
use App\Traits\SuperAdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class WithdrawResource extends Resource
{
    use SuperAdminAccess;

    protected static ?string $model = Withdraw::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string|UnitEnum|null $navigationGroup = 'Shareholders';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return WithdrawForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WithdrawsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWithdraws::route('/'),
        ];
    }
}
