<?php

namespace App\Filament\Resources\RobotResults\Tables;

use App\Models\PlayedGame;
use App\Support\AccountLookup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RobotResultsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('player_1')
                    ->label('Player')
                    ->formatStateUsing(fn (string $state) => AccountLookup::name($state)),
                TextColumn::make('player_2')
                    ->label('Robot (Player 2)')
                    ->formatStateUsing(fn () => 'Robot'),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->prefix('KES ')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('winner')
                    ->label('Winner')
                    ->formatStateUsing(function (string $state, PlayedGame $record): string {
                        if (empty($state)) {
                            return '—';
                        }

                        return $state === $record->player_2 ? 'Robot' : AccountLookup::name($state);
                    }),
                TextColumn::make('time')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->striped()
            ->defaultSort('time', 'desc');
    }
}
