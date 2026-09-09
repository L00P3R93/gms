<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Enums\ExpenseCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
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
                ->icon('heroicon-o-banknotes')
                ->schema([
                    Select::make('category')
                        ->options(ExpenseCategory::class)
                        ->enum(ExpenseCategory::class)
                        ->required()
                        ->native(false)
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
                ])->columns(2)->columnSpan(['lg' => 3]),
            Section::make('Receipt')
                ->icon('heroicon-o-paper-clip')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('receipt')
                        ->collection('receipt')
                        ->label('Upload Receipt')
                        ->helperText('PDF or image file, max 3MB')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'])
                        ->maxSize(3072)
                        ->downloadable()
                        ->previewable()
                        ->columnSpanFull(),
                ])->columnSpanFull(),
        ])->columns(3);
    }
}
