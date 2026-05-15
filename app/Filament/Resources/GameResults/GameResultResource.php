<?php

namespace App\Filament\Resources\GameResults;

use App\Filament\Resources\GameResults\Pages\ListGameResults;
use App\Filament\Resources\GameResults\Tables\GameResultsTable;
use App\Models\PlayedGame;
use App\Traits\SuperAdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class GameResultResource extends Resource
{
    use SuperAdminAccess;

    protected static ?string $model = PlayedGame::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-play-circle';

    protected static string|UnitEnum|null $navigationGroup = 'Game Results';

    protected static ?string $navigationLabel = 'Single Games';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'game-results';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('match_type', [PlayedGame::TYPE_MULTI_2, PlayedGame::TYPE_MULTI_3, PlayedGame::TYPE_MULTI_4]);
    }

    public static function table(Table $table): Table
    {
        return GameResultsTable::configure($table);
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
            'index' => ListGameResults::route('/'),
        ];
    }
}
