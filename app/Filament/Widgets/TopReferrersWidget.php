<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ReferralsPage;
use App\Filament\Pages\ReferralWithdrawalsPage;
use App\Services\GameApiService;
use App\Support\Format;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Log;

/**
 * The players who referred the most, from the `top_referrers` list in
 * `GET /stats/referrals` (shares the cached call with {@see ReferralProgrammeWidget}).
 */
class TopReferrersWidget extends BaseWidget
{
    protected static ?int $sort = 25;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return ReferralsPage::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Top Referrers')
            ->records(function (): array {
                try {
                    $referrers = app(GameApiService::class)->getPlayerReferralProgrammeStats()['top_referrers'] ?? [];
                } catch (\Throwable $e) {
                    Log::warning('Top referrers failed', ['error' => $e->getMessage()]);
                    $referrers = [];
                }

                return collect($referrers)
                    ->filter(fn ($row): bool => is_array($row) && isset($row['customer_id']))
                    ->keyBy(fn (array $row): string => (string) $row['customer_id'])
                    ->all();
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Player')
                    ->description(fn (array $record): string => '#'.$record['customer_id'])
                    ->color('primary')
                    ->url(fn (array $record): ?string => ReferralWithdrawalsPage::customerUrl($record['customer_id'])),
                TextColumn::make('referrals')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('earned')
                    ->alignEnd()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state): string => Format::money($state)),
            ])
            ->paginated(false)
            ->emptyStateHeading('No referrers yet');
    }
}
