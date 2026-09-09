<?php

namespace App\Filament\Resources\JackpotResults;

use App\Filament\Resources\JackpotResults\Pages\ListJackpotResults;
use App\Models\AccountSnapshot;
use BackedEnum;
use Filament\Resources\Resource;
use UnitEnum;

class JackpotResultResource extends Resource
{
    protected static ?string $model = AccountSnapshot::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static string|UnitEnum|null $navigationGroup = '🎯 Game Results';

    protected static ?string $navigationLabel = 'Jackpot Results';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'jackpot-results';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermissionTo('jackpot-results.view') ?? false;
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
