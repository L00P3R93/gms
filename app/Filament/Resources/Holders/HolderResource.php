<?php

namespace App\Filament\Resources\Holders;

use App\Filament\Resources\Holders\Pages\CreateHolder;
use App\Filament\Resources\Holders\Pages\EditHolder;
use App\Filament\Resources\Holders\Pages\ListHolders;
use App\Filament\Resources\Holders\Schemas\HolderForm;
use App\Filament\Resources\Holders\Tables\HoldersTable;
use App\Models\Holder;
use App\Traits\SuperAdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class HolderResource extends Resource
{
    use SuperAdminAccess;

    protected static ?string $model = Holder::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Shareholders';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return HolderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HoldersTable::configure($table);
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
            'index' => ListHolders::route('/'),
            'create' => CreateHolder::route('/create'),
            'edit' => EditHolder::route('/{record}/edit'),
        ];
    }
}
