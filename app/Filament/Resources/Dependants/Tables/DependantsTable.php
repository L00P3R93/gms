<?php

namespace App\Filament\Resources\Dependants\Tables;

use App\Enums\DependantStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DependantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('phone'),
                TextColumn::make('id_no')
                    ->label('ID Number'),
                TextColumn::make('holder.name')
                    ->label('Holder'),
                TextColumn::make('share_percent')
                    ->label('Share %')
                    ->suffix('%'),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('holder_id')
                    ->label('Holder')
                    ->relationship('holder', 'name'),
                SelectFilter::make('status')
                    ->options(DependantStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
