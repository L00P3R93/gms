<?php

namespace App\Filament\Widgets;

use App\Concerns\LoadsFinanceReport;
use App\Support\Format;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Admin-only list of the customers with the largest wallets over the last 30 days.
 */
class TopCustomersWidget extends BaseWidget
{
    use LoadsFinanceReport;

    protected static ?int $sort = 40;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Biggest Player Wallets (Last 30 Days)';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => collect($this->loadFinanceReport('customers/top', ['sort' => 'balance', 'limit' => 10])['items'] ?? [])
                ->filter(fn ($row): bool => is_array($row))
                ->values()
                ->all())
            ->columns([
                TextColumn::make('customer_name')
                    ->label('Player')
                    ->state(fn (array $record): string => filled($record['customer_name'] ?? null) ? $record['customer_name'] : 'Unnamed player')
                    ->weight('bold')
                    ->description(fn (array $record): string => '#'.($record['customer_id'] ?? '—')),
                TextColumn::make('deposited')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => Format::compactMoney($state)),
                TextColumn::make('staked')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => Format::compactMoney($state)),
                TextColumn::make('won')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => Format::compactMoney($state)),
                TextColumn::make('net_gaming')
                    ->label('Net Gaming')
                    ->alignEnd()
                    ->color(fn ($state): string => (float) $state >= 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state): string => Format::compactMoney($state)),
                TextColumn::make('balance')
                    ->alignEnd()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state): string => Format::compactMoney($state)),
            ])
            ->paginated(false)
            ->emptyStateHeading(fn (): string => $this->financeApiError ? 'Top customers unavailable' : 'No customer activity yet');
    }
}
