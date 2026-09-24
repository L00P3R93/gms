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
 * The players a customer referred, from `GET /customers/{id}/referrals`.
 */
class CustomerReferralsTable extends CustomerReferralTable
{
    public function table(Table $table): Table
    {
        return $this->configureReferralTable($table, 'Players they referred', 'No referrals yet')
            ->records(fn (int|string $page, int|string $recordsPerPage): LengthAwarePaginator => ApiTablePaginator::make(
                response: $this->fetchOrEmpty(fn (): array => app(GameApiService::class)->listCustomerReferrals($this->customerId, [
                    'page' => max(1, (int) $page),
                    'per_page' => (int) $recordsPerPage,
                ])),
                page: $page,
                perPage: $recordsPerPage,
            ))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Signed up')
                    ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
                TextColumn::make('referred_name')
                    ->label('Player')
                    ->description(fn (array $record): string => (filled($record['referred_id'] ?? null) ? '#'.$record['referred_id'].' · ' : '').($record['referred_phone'] ?? '—'))
                    ->color('primary')
                    ->url(fn (array $record): ?string => ReferralWithdrawalsPage::customerUrl($record['referred_id'] ?? null)),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => ReferralsPage::statusColor($state))
                    ->formatStateUsing(fn (?string $state): string => ReferralsPage::STATUSES[$state] ?? ucfirst((string) $state)),
                TextColumn::make('bonuses')
                    ->state(fn (array $record): array => ReferralsPage::bonusLabels($record))
                    ->badge()
                    ->color('success')
                    ->placeholder('None yet'),
                TextColumn::make('earned')
                    ->alignEnd()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state): string => Format::money($state)),
            ]);
    }
}
