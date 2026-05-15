<?php

namespace App\Filament\Resources\Accounts\Tables;

use App\Filament\Resources\Accounts\AccountResource;
use App\Services\GameApiService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Phone')
                    ->formatStateUsing(fn ($state) => '****'.substr($state, -4)),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('credit')
                    ->label('Game Credits')
                    ->numeric(thousandsSeparator: ',')
                    ->sortable(),
                TextColumn::make('game_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state == 1 ? 'Active' : 'Hidden')
                    ->color(fn ($state) => $state == 1 ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('game_status')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Hidden',
                    ]),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View Details')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('info')
                    ->url(fn ($record) => AccountResource::getUrl('view', ['record' => $record])),

                Action::make('editWallet')
                    ->label('Edit Wallet')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('warning')
                    ->mountUsing(function (Schema $form, $record) {
                        try {
                            $customer = app(GameApiService::class)->getCustomer($record->id);
                            $form->fill(['balance' => $customer['balance'] ?? 0]);
                        } catch (\Throwable $e) {
                            $form->fill(['balance' => 0]);
                            Notification::make()
                                ->title('Wallet API Unavailable')
                                ->body($e->getMessage())
                                ->warning()
                                ->send();
                        }
                    })
                    ->schema([
                        TextInput::make('balance')
                            ->label('Wallet Balance')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->prefix('KES')
                            ->prefixIcon(Heroicon::OutlinedBanknotes)
                            ->prefixIconColor('warning'),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            app(GameApiService::class)->updateCustomerWallet($record->id, (float) $data['balance']);
                            Notification::make()->title('Wallet updated')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Failed to update wallet')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('hide')
                    ->label('Hide Profile')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->game_status == 1)
                    ->action(fn ($record) => $record->update(['game_status' => 0])),

                Action::make('unhide')
                    ->label('Unhide Profile')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->game_status == 0)
                    ->action(fn ($record) => $record->update(['game_status' => 1])),
            ])
            ->striped()
            ->defaultSort('id', 'desc');
    }
}
