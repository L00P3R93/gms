<?php

namespace App\Filament\Resources\Payees;

use App\Filament\Resources\Payees\Pages\CreatePayee;
use App\Filament\Resources\Payees\Pages\EditPayee;
use App\Filament\Resources\Payees\Pages\ListPayees;
use App\Filament\Resources\Payees\Schemas\PayeeForm;
use App\Filament\Resources\Payees\Tables\PayeesTable;
use App\Models\Payee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class PayeeResource extends Resource
{
    protected static ?string $model = Payee::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|UnitEnum|null $navigationGroup = '💸 Expenses & Payouts';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return PayeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayeesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayees::route('/'),
            //            'create' => CreatePayee::route('/create'),
            //            'edit' => EditPayee::route('/{record}/edit'),
        ];
    }
}
