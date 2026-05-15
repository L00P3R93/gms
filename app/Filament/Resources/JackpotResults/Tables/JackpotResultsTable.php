<?php

namespace App\Filament\Resources\JackpotResults\Tables;

use App\Models\PlayedGame;
use App\Support\AccountLookup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JackpotResultsTable
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
                TextColumn::make('tier')
                    ->label('Tier')
                    ->getStateUsing(function (PlayedGame $record): string {
                        $name = strtolower($record->match_name);
                        if (str_contains($name, 'gold')) {
                            return 'Gold';
                        }
                        if (str_contains($name, 'silver')) {
                            return 'Silver';
                        }
                        if (str_contains($name, 'bronze')) {
                            return 'Bronze';
                        }

                        return '—';
                    })
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Gold' => 'warning',
                        'Silver' => 'gray',
                        'Bronze' => 'danger',
                        default => 'gray',
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
