<?php

namespace App\Filament\Resources\TournamentResults;

use App\Filament\Resources\TournamentResults\Pages\ListTournamentResults;
use App\Models\AccountSnapshot;
use App\Traits\SuperAdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use UnitEnum;

class TournamentResultResource extends Resource
{
    use SuperAdminAccess;

    protected static ?string $model = AccountSnapshot::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|UnitEnum|null $navigationGroup = 'Game Results';

    protected static ?string $navigationLabel = 'Tournament Results';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'tournament-results';

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
