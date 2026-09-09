<?php

namespace App\Filament\Resources\ApiIncomeLogs\Tables;

use App\Support\Format;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApiIncomeLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('api_total')
                            ->label('API Total')
                            ->formatStateUsing(fn ($state): string => Format::money($state))
                            ->weight('bold')
                            ->sortable(),
                        TextColumn::make('business_date')
                            ->label('Business Date')
                            ->date()
                            ->sortable()
                            ->color('gray')
                            ->size(TextSize::Small),
                    ]),
                    Stack::make([
                        TextColumn::make('created_at')
                            ->label('Checked At')
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
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }
}
