<?php

namespace App\Filament\Resources\Holders\Tables;

use App\Enums\HolderStatus;
use App\Filament\Resources\Dependants\DependantResource;
use App\Models\Holder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class HoldersTable
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
                TextColumn::make('share_percent')
                    ->label('Share %')
                    ->suffix('%'),
                TextColumn::make('wallet.balance')
                    ->label('Wallet Balance')
                    ->prefix('KES ')
                    ->numeric(2),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(HolderStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('withdraw')
                    ->label('Withdraw')
                    ->icon('heroicon-o-banknotes')
                    ->requiresConfirmation()
                    ->form([
                        TextInput::make('amount')
                            ->label('Amount (KES)')
                            ->numeric()
                            ->required()
                            ->minValue(1),
                    ])
                    ->action(function (Holder $record, array $data): void {
                        // Phase 5 — M-Pesa B2C logic
                    })
                    ->visible(fn (Holder $record) => $record->status === HolderStatus::Active),
                Action::make('view_dependants')
                    ->label('Dependants')
                    ->icon('heroicon-o-user-group')
                    ->url(fn (Holder $record) => DependantResource::getUrl('index', [
                        'tableFilters[holder_id][value]' => $record->id,
                    ])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
