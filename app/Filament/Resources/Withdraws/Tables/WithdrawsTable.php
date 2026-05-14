<?php

namespace App\Filament\Resources\Withdraws\Tables;

use App\Enums\WithdrawStatus;
use App\Enums\WithdrawType;
use App\Models\Withdraw;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WithdrawsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#'),
                TextColumn::make('receiver_name')
                    ->label('Receiver'),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('phone'),
                TextColumn::make('amount')
                    ->prefix('KES '),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('receipt')
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('response')
                    ->limit(40)
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(WithdrawStatus::class),
                SelectFilter::make('type')
                    ->options(WithdrawType::class),
                Filter::make('created_at')
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Withdraw $record): void {
                        // Phase 5 — M-Pesa B2C logic
                    })
                    ->visible(fn (Withdraw $record) => $record->status === WithdrawStatus::Pending),
            ])
            ->toolbarActions([]);
    }
}
