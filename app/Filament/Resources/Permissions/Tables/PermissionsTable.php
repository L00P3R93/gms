<?php

namespace App\Filament\Resources\Permissions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PermissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('name')
                            ->label('Name')
                            ->weight('bold')
                            ->sortable()
                            ->searchable(),
                        TextColumn::make('roles.name')
                            ->label('Roles')
                            ->badge()
                            ->listWithLineBreaks(false)
                            ->limitList(3)
                            ->expandableLimitedList(),
                    ]),
                    TextColumn::make('created_at')
                        ->label('Created At')
                        ->dateTime()
                        ->sortable()
                        ->visibleFrom('md'),
                ])->from('md'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('warning')
                    ->tooltip('Edit Permission'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
