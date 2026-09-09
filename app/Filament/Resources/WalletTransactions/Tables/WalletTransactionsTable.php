<?php

namespace App\Filament\Resources\WalletTransactions\Tables;

use App\Support\Format;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WalletTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('holder.name')
                            ->label('Shareholder')
                            ->weight('bold')
                            ->sortable()
                            ->searchable(),
                        TextColumn::make('description')
                            ->label('Description')
                            ->limit(50)
                            ->color('gray')
                            ->size(TextSize::Small),
                    ]),
                    Stack::make([
                        TextColumn::make('amount')
                            ->label('Amount')
                            ->formatStateUsing(fn ($state): string => Format::money($state))
                            ->weight('bold')
                            ->sortable()
                            ->color(fn (string $state): string => $state > 0 ? 'success' : 'danger'),
                        TextColumn::make('transaction_type')
                            ->label('Type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'credit' => 'success',
                                'debit' => 'danger',
                                default => 'gray',
                            }),
                    ])->visibleFrom('md'),
                    Stack::make([
                        TextColumn::make('balance_after')
                            ->label('Balance After')
                            ->formatStateUsing(fn ($state): string => Format::money($state))
                            ->color('gray')
                            ->size(TextSize::Small),
                        TextColumn::make('created_at')
                            ->label('Date')
                            ->dateTime()
                            ->sortable()
                            ->color('gray')
                            ->size(TextSize::Small),
                    ])->visibleFrom('md'),
                ])->from('md'),
                TextColumn::make('balance_before')
                    ->label('Balance Before')
                    ->formatStateUsing(fn ($state): string => Format::money($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('distribution_id')
                    ->label('Distribution ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }
}
