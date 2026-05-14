<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Enums\ExpenseCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Expense Details')
                ->columns(2)
                ->icon('heroicon-o-banknotes')
                ->schema([
                    Select::make('category')
                        ->options(ExpenseCategory::class)
                        ->enum(ExpenseCategory::class)
                        ->required()
                        ->prefixIcon(Heroicon::OutlinedTag)
                        ->prefixIconColor('info'),
                    TextInput::make('amount')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->prefix('KES')
                        ->suffixIcon(Heroicon::OutlinedBanknotes)
                        ->suffixIconColor('success'),
                    Textarea::make('description')
                        ->required()
                        ->columnSpanFull()
                        ->rows(3),
                ]),
        ]);
    }
}
