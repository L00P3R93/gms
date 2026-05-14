<?php

namespace App\Filament\Resources\Holders\Schemas;

use App\Enums\HolderStatus;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class HolderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Holder Details')
                ->columns(2)
                ->icon('heroicon-o-identification')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(80)
                        ->prefixIcon(Heroicon::OutlinedUser)
                        ->prefixIconColor('primary'),
                    TextInput::make('phone')
                        ->required()
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
                        ->minValue(0)
                        ->maxValue(100)
                        ->prefixIcon(Heroicon::OutlinedChartPie)
                        ->prefixIconColor('warning'),
                ]),
            Section::make('Status & User Link')
                ->columns(2)
                ->icon('heroicon-o-link')
                ->schema([
                    Select::make('status')
                        ->options(HolderStatus::class)
                        ->enum(HolderStatus::class)
                        ->required()
                        ->prefixIcon(Heroicon::OutlinedCheckCircle)
                        ->prefixIconColor('success'),
                    Select::make('user_id')
                        ->label('Linked User')
                        ->options(fn () => User::pluck('name', 'id')->toArray())
                        ->searchable()
                        ->nullable()
                        ->prefixIcon(Heroicon::OutlinedLink)
                        ->prefixIconColor('info'),
                ]),
        ]);
    }
}
