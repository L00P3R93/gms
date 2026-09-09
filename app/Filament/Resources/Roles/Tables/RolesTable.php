<?php

namespace App\Filament\Resources\Roles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('name')
                            ->label('Role Name')
                            ->weight('bold')
                            ->sortable()
                            ->searchable(),
                        TextColumn::make('created_at')
                            ->label('Created At')
                            ->date()
                            ->sortable()
                            ->color('gray')
                            ->size(TextSize::Small),
                    ]),
                    TextColumn::make('permissions.name')
                        ->label('Permissions')
                        ->badge()
                        ->listWithLineBreaks(false)
                        ->limitList(5)
                        ->expandableLimitedList()
                        ->visibleFrom('md'),
                ])->from('md'),
                TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
