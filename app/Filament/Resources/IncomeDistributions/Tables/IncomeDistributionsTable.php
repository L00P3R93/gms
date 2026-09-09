<?php

namespace App\Filament\Resources\IncomeDistributions\Tables;

use App\Support\Format;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IncomeDistributionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('delta')
                            ->label('Delta')
                            ->formatStateUsing(fn ($state): string => Format::money($state))
                            ->weight('bold')
                            ->sortable()
                            ->color('success'),
                        TextColumn::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'completed' => 'success',
                                'failed' => 'danger',
                                'pending' => 'warning',
                                default => 'gray',
                            }),
                    ]),
                    Stack::make([
                        TextColumn::make('current_total')
                            ->label('Current Total')
                            ->formatStateUsing(fn ($state): string => Format::money($state))
                            ->sortable(),
                        TextColumn::make('previous_total')
                            ->label('Previous Total')
                            ->formatStateUsing(fn ($state): string => Format::money($state))
                            ->sortable()
                            ->color('gray')
                            ->size(TextSize::Small),
                    ])->visibleFrom('md'),
                    Stack::make([
                        TextColumn::make('processed_at')
                            ->label('Processed At')
                            ->dateTime()
                            ->sortable()
                            ->color('gray')
                            ->size(TextSize::Small),
                        TextColumn::make('id')
                            ->label('ID')
                            ->sortable()
                            ->color('gray')
                            ->size(TextSize::Small),
                    ])->visibleFrom('md'),
                ])->from('md'),
            ])
            ->defaultSort('processed_at', 'desc')
            ->paginated([25, 50, 100]);
    }
}
