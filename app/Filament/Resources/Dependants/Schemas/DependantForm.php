<?php

namespace App\Filament\Resources\Dependants\Schemas;

use App\Enums\DependantStatus;
use App\Models\Holder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DependantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dependant Details')
                ->columns(2)
                ->icon('heroicon-o-user-group')
                ->schema([
                    Select::make('holder_id')
                        ->label('Holder')
                        ->options(fn () => Holder::pluck('name', 'id')->toArray())
                        ->searchable()
                        ->required()
                        ->prefixIcon(Heroicon::OutlinedUser)
                        ->prefixIconColor('primary')
                        ->columnSpanFull(),
                    TextInput::make('name')
                        ->required()
                        ->maxLength(80)
                        ->prefixIcon(Heroicon::OutlinedUser)
                        ->prefixIconColor('primary'),
                    TextInput::make('phone')
                        ->maxLength(50)
                        ->prefix('254')
                        ->prefixIcon(Heroicon::OutlinedPhone)
                        ->prefixIconColor('success'),
                    TextInput::make('id_no')
                        ->label('ID Number')
                        ->maxLength(50)
                        ->prefixIcon(Heroicon::OutlinedIdentification)
                        ->prefixIconColor('info'),
                    TextInput::make('share')
                        ->label('Share')
                        ->numeric()
                        ->required()
                        ->suffix('%')
                        ->prefixIcon(Heroicon::OutlinedChartPie)
                        ->prefixIconColor('warning'),
                ]),
            Section::make('Status')
                ->columns(1)
                ->icon('heroicon-o-check-badge')
                ->schema([
                    Select::make('status')
                        ->options(DependantStatus::class)
                        ->enum(DependantStatus::class)
                        ->required()
                        ->prefixIcon(Heroicon::OutlinedCheckCircle)
                        ->prefixIconColor('success'),
                ]),
        ]);
    }
}
