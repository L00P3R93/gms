<?php

namespace App\Filament\Resources\Roles\Tables;

use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->date(),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->before(function (Role $record): void {
                        abort_if(
                            in_array($record->name, ['super-admin', 'admin', 'director', 'agent']),
                            403,
                            'Cannot delete a protected role.'
                        );
                    })
                    ->visible(fn (Role $record) => ! in_array($record->name, ['super-admin', 'admin', 'director', 'agent'])),
            ])
            ->toolbarActions([]);
    }
}
