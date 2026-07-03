<?php

namespace App\Filament\Resources\IncomeDistributions;

use App\Filament\Resources\IncomeDistributions\Pages\ListIncomeDistributions;
use App\Filament\Resources\IncomeDistributions\Pages\ViewIncomeDistribution;
use App\Filament\Resources\IncomeDistributions\Tables\IncomeDistributionsTable;
use App\Models\IncomeDistribution;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class IncomeDistributionResource extends Resource
{
    protected static ?string $model = IncomeDistribution::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string|UnitEnum|null $navigationGroup = 'Financial';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return IncomeDistributionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIncomeDistributions::route('/'),
            'view' => ViewIncomeDistribution::route('/{record}'),
        ];
    }
}
