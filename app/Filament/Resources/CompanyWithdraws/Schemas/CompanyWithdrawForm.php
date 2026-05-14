<?php

namespace App\Filament\Resources\CompanyWithdraws\Schemas;

use App\Models\CompanyWallet;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyWithdrawForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Withdrawal Request')
                ->description('Submit a company wallet withdrawal.')
                ->columns(2)
                ->icon('heroicon-o-building-library')
                ->schema([
                    TextInput::make('phone')
                        ->required()
                        ->regex('/^254[0-9]{9}$/')
                        ->placeholder('254XXXXXXXXX')
                        ->label('M-Pesa Phone'),
                    TextInput::make('amount')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->prefix('KES')
                        ->rules([
                            fn () => function (string $attribute, $value, \Closure $fail): void {
                                $balance = CompanyWallet::find(CompanyWallet::MAIN_WALLET)?->balance ?? 0;

                                if ($value > $balance) {
                                    $fail('Amount exceeds company wallet balance of KES '.number_format($balance, 2));
                                }
                            },
                        ]),
                    Textarea::make('reason')
                        ->required()
                        ->columnSpanFull()
                        ->rows(3),
                ]),
        ]);
    }
}
