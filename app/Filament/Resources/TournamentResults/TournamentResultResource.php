<?php

namespace App\Filament\Resources\TournamentResults;

use App\Filament\Resources\TournamentResults\Pages\ListTournamentResults;
use App\Filament\Resources\TournamentResults\Tables\TournamentResultsTable;
use App\Models\PlayedGame;
use App\Traits\SuperAdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TournamentResultResource extends Resource
{
    use SuperAdminAccess;

    protected static ?string $model = PlayedGame::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|UnitEnum|null $navigationGroup = 'Game Results';

    protected static ?string $navigationLabel = 'Tournament Results';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'tournament-results';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('match_type', PlayedGame::TYPE_TOURNAMENT);
    }

    public static function table(Table $table): Table
    {
        return TournamentResultsTable::configure($table);
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
            'index' => ListTournamentResults::route('/'),
        ];
    }
}
