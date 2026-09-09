<?php

namespace App\Filament\Resources\Holders\Tables;

use App\Enums\HolderStatus;
use App\Enums\WithdrawStatus;
use App\Enums\WithdrawType;
use App\Filament\Exports\HolderExporter;
use App\Filament\Imports\HolderImporter;
use App\Filament\Resources\Dependants\DependantResource;
use App\Models\Holder;
use App\Models\Withdraw;
use App\Services\MpesaService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
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
                Split::make([
                    Stack::make([
                        TextColumn::make('name')
                            ->weight('bold')
                            ->searchable(),
                        TextColumn::make('phone')
                            ->color('gray')
                            ->size(TextSize::Small),
                    ]),
                    Stack::make([
                        TextColumn::make('wallet.balance')
                            ->label('Wallet Balance')
                            ->prefix('KES ')
                            ->numeric(2)
                            ->weight('bold'),
                        TextColumn::make('share_percent')
                            ->label('Share %')
                            ->suffix('%')
                            ->color('gray')
                            ->size(TextSize::Small),
                    ])->visibleFrom('md'),
                    Stack::make([
                        TextColumn::make('status')
                            ->badge(),
                        TextColumn::make('id_no')
                            ->label('ID')
                            ->color('gray')
                            ->size(TextSize::Small),
                    ])->visibleFrom('md'),
                ])->from('md'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(HolderStatus::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('warning')
                    ->tooltip('Edit Holder'),
                Action::make('withdraw')
                    ->iconButton()
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->tooltip('Withdraw Funds')
                    ->requiresConfirmation(false)
                    ->form([
                        TextInput::make('amount')
                            ->label('Amount (KES)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->prefix('KES'),
                    ])
                    ->action(function (Holder $record, array $data): void {
                        if ($record->status !== HolderStatus::Active) {
                            Notification::make()->title('Holder is inactive')->danger()->send();

                            return;
                        }

                        $wallet = $record->wallet;
                        if (! $wallet || $wallet->balance < $data['amount']) {
                            Notification::make()->title('Insufficient wallet balance')->danger()->send();

                            return;
                        }

                        $withdraw = Withdraw::create([
                            'receiver_id' => $record->id,
                            'type' => WithdrawType::Holder->value,
                            'phone' => $record->phone,
                            'amount' => $data['amount'],
                            'status' => WithdrawStatus::Pending->value,
                        ]);

                        try {
                            $response = app(MpesaService::class)->b2c(
                                $record->phone,
                                $data['amount'],
                                'Shareholder Payout'
                            );

                            $conversationId = $response['ConversationID'] ?? null;

                            if ($conversationId) {
                                $withdraw->update([
                                    'status' => WithdrawStatus::Processing->value,
                                    'receipt' => $conversationId,
                                    'conversation_id' => $conversationId,
                                    'response' => $response['ResponseDescription'] ?? '',
                                ]);

                                $wallet->decrement('balance', $data['amount']);

                                $notification = Notification::make()
                                    ->title('Withdrawal initiated')
                                    ->body("M-Pesa ConversationID: {$conversationId}")
                                    ->success();
                                $notification->send();
                                $notification->sendToDatabase(auth()->user());
                            } else {
                                $withdraw->update([
                                    'status' => WithdrawStatus::Failed->value,
                                    'response' => json_encode($response),
                                ]);
                                Notification::make()->title('No ConversationID in M-Pesa response')->warning()->send();
                            }
                        } catch (\Exception $e) {
                            $withdraw->update([
                                'status' => WithdrawStatus::Failed->value,
                                'response' => $e->getMessage(),
                            ]);
                            Notification::make()->title('M-Pesa B2C failed')->body($e->getMessage())->danger()->send();
                        }
                    })
                    ->visible(fn (Holder $record) => $record->status === HolderStatus::Active),
                Action::make('view_dependants')
                    ->iconButton()
                    ->icon('heroicon-o-user-group')
                    ->color('info')
                    ->tooltip('View Dependants')
                    ->url(fn (Holder $record) => DependantResource::getUrl('index', [
                        'tableFilters[holder_id][value]' => $record->id,
                    ])),
            ])
            ->toolbarActions([
                ImportAction::make()
                    ->label('Import Holders')
                    ->icon('hugeicons-file-import')
                    ->color('info')
                    ->importer(HolderImporter::class),

                ExportAction::make()
                    ->label('Export Holders')
                    ->icon('hugeicons-file-export')
                    ->color('success')
                    ->exporter(HolderExporter::class),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
