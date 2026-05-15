<?php

namespace App\Filament\Resources\TournamentResults\Tables;

use App\Models\PlayedGame;
use App\Support\AccountLookup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TournamentResultsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('match_name')
                    ->label('Competition ID')
                    ->searchable(),
                TextColumn::make('players')
                    ->label('Players')
                    ->getStateUsing(fn (PlayedGame $record): string => collect([
                        $record->player_1,
                        $record->player_2,
                        $record->player_3,
                        $record->player_4,
                        $record->player_5,
                        $record->player_6,
                    ])->filter()->map(fn ($id) => AccountLookup::name($id))->join(', ')),
                TextColumn::make('amount')
                    ->label('Buy-In')
                    ->prefix('KES ')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('winner')
                    ->label('Winner')
                    ->formatStateUsing(fn (string $state) => AccountLookup::name($state)),
                TextColumn::make('rounds')
                    ->label('Rounds')
                    ->getStateUsing(function (PlayedGame $record): string {
                        // Naming convention: tn_{rounds}_{buy_in}
                        $parts = explode('_', $record->match_name);

                        return isset($parts[1]) && is_numeric($parts[1]) ? $parts[1] : '—';
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
