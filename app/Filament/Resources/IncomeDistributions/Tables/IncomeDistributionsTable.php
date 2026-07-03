<?php

namespace App\Filament\Resources\IncomeDistributions\Tables;

use App\Support\Format;
use Filament\Tables;
use Filament\Tables\Table;

class IncomeDistributionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('previous_total')
                    ->label('Previous Total')
                    ->formatStateUsing(fn ($state): string => Format::money($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('current_total')
                    ->label('Current Total')
                    ->formatStateUsing(fn ($state): string => Format::money($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('delta')
                    ->label('Delta')
                    ->formatStateUsing(fn ($state): string => Format::money($state))
                    ->sortable()
                    ->color('success')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Processed At')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('processed_at', 'desc')
            ->paginated([25, 50, 100]);
    }
}
