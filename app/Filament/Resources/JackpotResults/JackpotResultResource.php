<?php

namespace App\Filament\Resources\JackpotResults;

use App\Filament\Resources\JackpotResults\Pages\ListJackpotResults;
use App\Filament\Resources\JackpotResults\Tables\JackpotResultsTable;
use App\Models\PlayedGame;
use App\Traits\SuperAdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class JackpotResultResource extends Resource
{
    use SuperAdminAccess;

    protected static ?string $model = PlayedGame::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static string|UnitEnum|null $navigationGroup = 'Game Results';

    protected static ?string $navigationLabel = 'Jackpot Results';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'jackpot-results';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('match_type', PlayedGame::TYPE_JACKPOT);
    }

    public static function table(Table $table): Table
    {
        return JackpotResultsTable::configure($table);
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
            'index' => ListJackpotResults::route('/'),
        ];
    }
}
