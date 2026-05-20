<?php

namespace App\Filament\Resources\RobotResults;

use App\Filament\Resources\RobotResults\Pages\ListRobotResults;
use App\Models\AccountSnapshot;
use App\Traits\SuperAdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use UnitEnum;

class RobotResultResource extends Resource
{
    use SuperAdminAccess;

    protected static ?string $model = AccountSnapshot::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string|UnitEnum|null $navigationGroup = 'Game Results';

    protected static ?string $navigationLabel = 'Robot Games';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'robot-results';

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
            'index' => ListRobotResults::route('/'),
        ];
    }
}
