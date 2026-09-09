<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use STS\FilamentImpersonate\Actions\Impersonate;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('name')
                            ->weight('bold')
                            ->searchable()
                            ->sortable(),
                        TextColumn::make('userName')
                            ->label('Username')
                            ->searchable()
                            ->color('gray')
                            ->size(TextSize::Small),
                    ]),
                    Stack::make([
                        TextColumn::make('email')
                            ->copyable()
                            ->searchable()
                            ->color('gray')
                            ->size(TextSize::Small),
                        TextColumn::make('roles.name')
                            ->label('Role')
                            ->badge(),
                    ])->visibleFrom('md'),
                    Stack::make([
                        TextColumn::make('status')
                            ->badge()
                            ->sortable(),
                        TextColumn::make('created_at')
                            ->label('Created')
                            ->date()
                            ->sortable()
                            ->color('gray')
                            ->size(TextSize::Small),
                    ])->visibleFrom('md'),
                ])->from('md'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(UserStatus::class),
                SelectFilter::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name'),
            ])
            ->recordActions([
                EditAction::make()->iconButton()->icon(Heroicon::OutlinedPencilSquare)->color('warning')->tooltip('Edit User'),
                DeleteAction::make()->iconButton()->icon(Heroicon::OutlinedTrash)->color('danger')->tooltip('Delete User'),
                Impersonate::make()
                    ->iconButton()
                    ->icon('hugeicons-user-switch')
                    ->color('indigo')
                    ->tooltip('Impersonate User')
                    ->visible(fn ($record) => auth()->user()->isAdmin() && ! $record->isAdmin())
                    ->redirectTo(url('/console')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
