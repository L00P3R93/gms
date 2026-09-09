<?php

namespace App\Filament\Resources\Payouts\Schemas;

use App\Models\Payee;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PayoutForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payout Details')
                ->columns(2)
                ->icon('heroicon-o-banknotes')
                ->schema([
                    Select::make('payee_id')
                        ->label('Payee')
                        ->relationship('payee', 'name')
                        ->getOptionLabelFromRecordUsing(fn (Payee $record) => "{$record->name} — {$record->phone} ({$record->designation})")
                        ->searchable(['name', 'phone'])
                        ->createOptionForm([
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
                                ])->columns(2)->columnSpan(['lg' => 3]),
                        ])
                        ->createOptionAction(function (Action $action) {
                            return $action
                                ->modalHeading('Add Payee')
                                ->modalDescription('Create a new payee and select it')
                                ->modalSubmitActionLabel('Add Payee');
                        })
                        ->preload()
                        ->required()
                        ->prefixIcon(Heroicon::OutlinedUser)
                        ->prefixIconColor('primary')
                        ->helperText('Search by payee name or phone number'),
                    TextInput::make('amount')
                        ->label('Amount (KES)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->prefix('KES')
                        ->suffixIcon(Heroicon::OutlinedBanknotes)
                        ->suffixIconColor('success'),
                ])->columns(2)->columnSpan(['lg' => 3]),
            Section::make('Payout Reason')->schema([
                Textarea::make('reason')
                    ->required()
                    ->columnSpanFull()
                    ->rows(3)
                    ->placeholder('Describe the reason for this payout…'),
            ])->columnSpanFull(),
        ])->columns(3);
    }
}
