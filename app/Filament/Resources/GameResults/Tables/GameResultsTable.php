<?php

namespace App\Filament\Resources\GameResults\Tables;

use App\Models\PlayedGame;
use App\Support\AccountLookup;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GameResultsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('match_type')
                    ->label('Match Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        PlayedGame::TYPE_MULTI_2 => '2 Players',
                        PlayedGame::TYPE_MULTI_3 => '3 Players',
                        PlayedGame::TYPE_MULTI_4 => '4 Players',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        PlayedGame::TYPE_MULTI_2 => 'info',
                        PlayedGame::TYPE_MULTI_3 => 'warning',
                        PlayedGame::TYPE_MULTI_4 => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('players')
                    ->label('Players')
                    ->getStateUsing(fn (PlayedGame $record): string => collect([
                        $record->player_1,
                        $record->player_2,
                        $record->player_3,
                        $record->player_4,
                    ])->filter()->map(fn ($id) => AccountLookup::name($id))->join(', ')),
                TextColumn::make('amount')
                    ->label('Total Bet')
                    ->prefix('KES ')
                    ->numeric()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total Bets (KES)')),
                TextColumn::make('winner')
                    ->label('Winner')
                    ->formatStateUsing(fn (string $state) => AccountLookup::name($state)),
                TextColumn::make('win_amount')
                    ->label('Win Amount')
                    ->getStateUsing(fn (PlayedGame $record): string => 'KES '.number_format($record->amount * 0.90, 2)),
                TextColumn::make('income')
                    ->label('Income')
                    ->getStateUsing(fn (PlayedGame $record): string => 'KES '.number_format($record->amount * 0.10, 2))
                    ->summarize(
                        Sum::make()
                            ->label('Total Income (KES)')
                            ->using(fn (Builder $query): float => round($query->sum(DB::raw('amount * 0.10')), 2))
                    ),
                TextColumn::make('time')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('time')
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn (Builder $q, $date) => $q->where('time', '>=', $date))
                        ->when($data['until'], fn (Builder $q, $date) => $q->where('time', '<=', $date))
                    ),
                SelectFilter::make('match_type')
                    ->label('Match Type')
                    ->options([
                        PlayedGame::TYPE_MULTI_2 => '2 Players',
                        PlayedGame::TYPE_MULTI_3 => '3 Players',
                        PlayedGame::TYPE_MULTI_4 => '4 Players',
                    ]),
            ])
            ->striped()
            ->defaultSort('time', 'desc');
    }
}
