<?php

namespace App\Filament\Resources\Payees\Schemas;

use App\Enums\PayeeStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PayeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payee Details')
                ->columns(2)
                ->icon('heroicon-o-user')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->prefixIcon(Heroicon::OutlinedUser)
                        ->prefixIconColor('primary'),
                    TextInput::make('phone')
                        ->required()
                        ->tel()
                        ->maxLength(20)
                        ->placeholder('2547XXXXXXXX or 2541XXXXXXXX')
                        ->regex('/^(?:0|254)[0-9]{9}$/')
                        ->validationMessages([
                            'regex' => 'Phone must be in format 2547XXXXXXXX, 2541XXXXXXXX, or 07XXXXXXXX.',
                        ])
                        ->prefixIcon(Heroicon::OutlinedPhone)
                        ->prefixIconColor('info'),
                    TextInput::make('email')
                        ->email()
                        ->nullable()
                        ->maxLength(255)
                        ->prefixIcon(Heroicon::OutlinedEnvelope)
                        ->prefixIconColor('gray'),
                    TextInput::make('designation')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g. Accountant, Director')
                        ->prefixIcon(Heroicon::OutlinedBriefcase)
                        ->prefixIconColor('warning'),
                    TextInput::make('team')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g. Finance, Operations')
                        ->prefixIcon(Heroicon::OutlinedUserGroup)
                        ->prefixIconColor('success'),
                    Select::make('status')
                        ->options(PayeeStatus::class)
                        ->enum(PayeeStatus::class)
                        ->required()
                        ->native(false)
                        ->default(PayeeStatus::Active->value)
                        ->prefixIcon(Heroicon::OutlinedCheckCircle)
                        ->prefixIconColor('success'),
                ])->columns(2)->columnSpan(['lg' => 3]),
        ])->columns(3);
    }
}
