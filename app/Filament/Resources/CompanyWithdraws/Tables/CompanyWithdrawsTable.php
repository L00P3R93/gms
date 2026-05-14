<?php

namespace App\Filament\Resources\CompanyWithdraws\Tables;

use App\Enums\CompanyWithdrawStatus;
use App\Models\CompanyWithdraw;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompanyWithdrawsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#'),
                TextColumn::make('phone'),
                TextColumn::make('amount')
                    ->prefix('KES '),
                TextColumn::make('reason')
                    ->limit(40),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('requester.name')
                    ->label('Requested By'),
                TextColumn::make('approver.name')
                    ->label('Approved By')
                    ->placeholder('—'),
                TextColumn::make('receipt')
                    ->label('M-Pesa Receipt')
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date(),
            ])
            ->filters([])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve & Pay')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('This will trigger an M-Pesa B2C payment. This cannot be undone.')
                    ->action(function (CompanyWithdraw $record): void {
                        // Phase 5 — MpesaService::b2c() called here
                    })
                    ->visible(fn (CompanyWithdraw $record) => $record->status === CompanyWithdrawStatus::Pending),
            ])
            ->toolbarActions([]);
    }
}
