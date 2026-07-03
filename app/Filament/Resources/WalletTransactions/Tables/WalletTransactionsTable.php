<?php

namespace App\Filament\Resources\WalletTransactions\Tables;

use App\Support\Format;
use Filament\Tables;
use Filament\Tables\Table;

class WalletTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('holder.name')
                    ->label('Shareholder')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('distribution_id')
                    ->label('Distribution ID')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state): string => Format::money($state))
                    ->sortable()
                    ->color(fn (string $state): string => $state > 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('balance_before')
                    ->label('Balance Before')
                    ->formatStateUsing(fn ($state): string => Format::money($state))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Balance After')
                    ->formatStateUsing(fn ($state): string => Format::money($state))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('transaction_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'credit' => 'success',
                        'debit' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }
}
