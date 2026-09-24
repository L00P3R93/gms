<?php

namespace App\Livewire;

use App\Filament\Pages\ReferralWithdrawalDetailPage;
use App\Filament\Pages\ReferralWithdrawalsPage;
use App\Services\GameApiService;
use App\Support\ApiTablePaginator;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * A customer's referral withdrawals to M-Pesa, from
 * `GET /customers/{id}/referral-wallet/withdrawals`. Each row opens the
 * withdrawal page, where finance can settle a stuck one.
 */
class CustomerReferralWithdrawalsTable extends CustomerReferralTable
{
    public function table(Table $table): Table
    {
        return $this->configureReferralTable($table, 'Referral withdrawals', 'No referral withdrawals yet')
            ->records(fn (int|string $page, int|string $recordsPerPage): LengthAwarePaginator => ApiTablePaginator::make(
                response: $this->fetchOrEmpty(fn (): array => app(GameApiService::class)->listCustomerReferralWithdrawals($this->customerId, [
                    'page' => max(1, (int) $page),
                    'per_page' => (int) $recordsPerPage,
                ])),
                page: $page,
                perPage: $recordsPerPage,
            ))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Requested')
                    ->formatStateUsing(fn ($state): string => Format::dateTime($state))
                    ->description(fn (array $record): ?string => ReferralWithdrawalsPage::isStuck($record) ? 'Stuck — over '.ReferralWithdrawalsPage::STUCK_AFTER_HOURS.'h' : null)
                    ->color(fn (array $record): ?string => ReferralWithdrawalsPage::isStuck($record) ? 'danger' : null),
                TextColumn::make('amount')
                    ->alignEnd()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state): string => Format::money($state)),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => ReferralWithdrawalsPage::statusColor($state))
                    ->formatStateUsing(fn (?string $state): string => ReferralWithdrawalsPage::STATUSES[$state] ?? ucfirst((string) $state)),
                TextColumn::make('mpesa_receipt')
                    ->label('M-Pesa receipt')
                    ->placeholder('—')
                    ->fontFamily('mono'),
            ])
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedEye)
                    ->color('info')
                    ->tooltip('View withdrawal')
                    ->visible(fn (array $record): bool => filled($record['id'] ?? null))
                    ->url(fn (array $record): ?string => filled($record['id'] ?? null) ? ReferralWithdrawalDetailPage::getUrl(['withdrawal' => $record['id']]) : null),
            ]);
    }
}
