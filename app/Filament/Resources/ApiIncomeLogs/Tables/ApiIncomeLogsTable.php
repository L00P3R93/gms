<?php

namespace App\Filament\Resources\ApiIncomeLogs\Tables;

use App\Support\Format;
use Filament\Tables;
use Filament\Tables\Table;

class ApiIncomeLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('api_total')
                    ->label('API Total')
                    ->formatStateUsing(fn ($state): string => Format::money($state))
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('business_date')
                    ->label('Business Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Checked At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }
}
