<?php

namespace App\Filament\Resources\Withdraws\Tables;

use App\Enums\WithdrawStatus;
use App\Enums\WithdrawType;
use App\Models\Withdraw;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
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
                Split::make([
                    Stack::make([
                        TextColumn::make('receiver_name')
                            ->label('Receiver')
                            ->weight('bold'),
                        TextColumn::make('type')
                            ->badge(),
                    ]),
                    Stack::make([
                        TextColumn::make('amount')
                            ->prefix('KES ')
                            ->weight('bold'),
                        TextColumn::make('status')
                            ->badge(),
                    ])->visibleFrom('md'),
                    Stack::make([
                        TextColumn::make('receipt')
                            ->copyable()
                            ->placeholder('—'),
                        TextColumn::make('created_at')
                            ->label('Date')
                            ->date()
                            ->color('gray')
                            ->size(TextSize::Small),
                    ])->visibleFrom('md'),
                ])->from('md'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(WithdrawStatus::class),
                SelectFilter::make('type')
                    ->options(WithdrawType::class),
                Filter::make('created_at')
                    ->label('Date Range')
                    ->schema([
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
                    ->iconButton()
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->tooltip('Approve Withdrawal')
                    ->requiresConfirmation()
                    ->action(function (Withdraw $record): void {
                        // Phase 5 — M-Pesa B2C logic
                    })
                    ->visible(fn (Withdraw $record) => $record->status === WithdrawStatus::Pending),
            ])
            ->toolbarActions([]);
    }
}
