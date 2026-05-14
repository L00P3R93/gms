<?php

namespace App\Filament\Resources\CompanyWithdraws\Tables;

use App\Enums\CompanyWithdrawStatus;
use App\Models\CompanyWallet;
use App\Models\CompanyWithdraw;
use App\Services\MpesaService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
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
                    ->modalHeading('Approve Company Withdrawal')
                    ->modalDescription(fn (CompanyWithdraw $record) => "Send KES {$record->amount} to {$record->phone} via M-Pesa B2C. This cannot be undone.")
                    ->action(function (CompanyWithdraw $record): void {
                        if ($record->status !== CompanyWithdrawStatus::Pending) {
                            Notification::make()->title('Already processed')->warning()->send();

                            return;
                        }

                        $companyWallet = CompanyWallet::find(CompanyWallet::MAIN_WALLET);
                        if (! $companyWallet || $companyWallet->balance < $record->amount) {
                            Notification::make()->title('Insufficient company wallet balance')->danger()->send();

                            return;
                        }

                        try {
                            $response = app(MpesaService::class)->b2c(
                                $record->phone,
                                $record->amount,
                                $record->reason ?? 'Company Withdrawal'
                            );

                            $conversationId = $response['ConversationID'] ?? null;

                            if ($conversationId) {
                                $record->update([
                                    'status' => CompanyWithdrawStatus::Processing->value,
                                    'receipt' => $conversationId,
                                    'conversation_id' => $conversationId,
                                    'response' => $response['ResponseDescription'] ?? '',
                                    'approved_by' => auth()->id(),
                                ]);

                                $companyWallet->decrement('balance', $record->amount);

                                $notification = Notification::make()
                                    ->title('Payment initiated')
                                    ->body("ConversationID: {$conversationId}")
                                    ->success();
                                $notification->send();
                                $notification->sendToDatabase(auth()->user());
                            } else {
                                $record->update([
                                    'status' => CompanyWithdrawStatus::Failed->value,
                                    'response' => json_encode($response),
                                ]);
                                Notification::make()->title('No ConversationID in M-Pesa response')->warning()->send();
                            }
                        } catch (\Exception $e) {
                            $record->update([
                                'status' => CompanyWithdrawStatus::Failed->value,
                                'response' => $e->getMessage(),
                            ]);
                            Notification::make()->title('M-Pesa B2C failed')->body($e->getMessage())->danger()->send();
                        }
                    })
                    ->visible(fn (CompanyWithdraw $record) => $record->status === CompanyWithdrawStatus::Pending),
            ])
            ->toolbarActions([]);
    }
}
