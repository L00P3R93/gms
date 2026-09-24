<?php

namespace App\Livewire;

use App\Filament\Pages\ReferralsPage;
use App\Filament\Pages\ReferralWithdrawalsPage;
use App\Services\GameApiService;
use App\Support\ApiTablePaginator;
use App\Support\Format;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Bonuses credited to a customer's referral wallet, from
 * `GET /customers/{id}/referral-wallet`, which pages under `pagination`.
 */
class CustomerReferralBonusesTable extends CustomerReferralTable
{
    public function table(Table $table): Table
    {
        return $this->configureReferralTable($table, 'Referral wallet bonuses', 'No bonuses yet')
            ->records(function (int|string $page, int|string $recordsPerPage): LengthAwarePaginator {
                $wallet = $this->fetchOrEmpty(fn (): array => app(GameApiService::class)->getCustomerReferralWallet($this->customerId, [
                    'page' => max(1, (int) $page),
                    'per_page' => (int) $recordsPerPage,
                ]));

                return ApiTablePaginator::fromReport([
                    'items' => $wallet['data']['bonuses'] ?? [],
                    'pagination' => $wallet['pagination'] ?? [],
                ]);
            })
            ->columns([
                TextColumn::make('created_at')
                    ->label('Credited')
                    ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
                TextColumn::make('milestone')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn (?string $state): string => ReferralsPage::MILESTONES[$state] ?? ucfirst(str_replace('_', ' ', (string) $state))),
                TextColumn::make('referred_name')
                    ->label('For referring')
                    ->description(fn (array $record): ?string => filled($record['referred_id'] ?? null) ? '#'.$record['referred_id'] : null)
                    ->color('primary')
                    ->url(fn (array $record): ?string => ReferralWithdrawalsPage::customerUrl($record['referred_id'] ?? null)),
                TextColumn::make('amount')
                    ->alignEnd()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state): string => Format::money($state)),
            ]);
    }
}
