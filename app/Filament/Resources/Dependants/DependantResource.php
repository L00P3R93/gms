<?php

namespace App\Filament\Resources\Dependants;

use App\Filament\Resources\Dependants\Pages\CreateDependant;
use App\Filament\Resources\Dependants\Pages\EditDependant;
use App\Filament\Resources\Dependants\Pages\ListDependants;
use App\Filament\Resources\Dependants\Schemas\DependantForm;
use App\Filament\Resources\Dependants\Tables\DependantsTable;
use App\Models\Dependant;
use App\Traits\SuperAdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class DependantResource extends Resource
{
    use SuperAdminAccess;

    protected static ?string $model = Dependant::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Shareholders';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return DependantForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DependantsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDependants::route('/'),
            'create' => CreateDependant::route('/create'),
            'edit' => EditDependant::route('/{record}/edit'),
        ];
    }
}
