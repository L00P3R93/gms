<?php

namespace App\Livewire;

use App\Services\GameApiService;
use App\Support\Format;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;

/**
 * A customer's signup bonuses on the Promotions tab, from
 * `GET /customers/{id}/promotions`: the code used, what they received, how much
 * they have staked towards unlocking it and what is still locked. Explains why a
 * withdrawal was refused with `signup_bonus_locked`. Mounted lazily, so nothing
 * is fetched until the tab is opened.
 */
class CustomerPromotionsTable extends TableWidget
{
    public const LOCKED_EXPLANATION = 'A locked bonus can only be staked on games, tournaments and jackpots. A withdrawal, wallet transfer or coin purchase that would use it is refused with signup_bonus_locked.';

    public int $customerId;

    public bool $apiError = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * Per-request memo so the heading and the rows share one KadiApi call.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $promotions = null;

    public function mount(int $customerId): void
    {
        $this->customerId = $customerId;
    }

    /**
     * @return array<string, mixed>
     */
    protected function promotions(): array
    {
        if ($this->promotions !== null) {
            return $this->promotions;
        }

        try {
            $this->promotions = app(GameApiService::class)->getCustomerPromotions($this->customerId);
            $this->apiError = false;
        } catch (\Throwable $e) {
            Log::warning('Customer promotions failed', ['customer' => $this->customerId, 'error' => $e->getMessage()]);
            $this->apiError = true;
            $this->promotions = [];
        }

        return $this->promotions;
    }

    /**
     * @param  array<string, mixed>  $promotion
     */
    public static function stakedLabel(array $promotion): string
    {
        return Format::money($promotion['wagered'] ?? 0).' of '.Format::money($promotion['net_amount'] ?? 0);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Signup bonus')
            ->description(function (): string {
                $locked = (float) ($this->promotions()['locked_amount'] ?? 0);

                return $locked > 0
                    ? Format::money($locked).' still locked. '.self::LOCKED_EXPLANATION
                    : self::LOCKED_EXPLANATION;
            })
            ->records(fn (): array => collect($this->promotions()['items'] ?? [])
                ->filter(fn ($row): bool => is_array($row))
                ->values()
                ->all())
            ->columns([
                TextColumn::make('promo_code')
                    ->label('Code')
                    ->fontFamily('mono')
                    ->weight('bold'),
                TextColumn::make('granted_at')
                    ->label('Granted')
                    ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
                TextColumn::make('net_amount')
                    ->label('Received')
                    ->alignEnd()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state): string => Format::money($state))
                    ->description(fn (array $record): string => 'House paid '.Format::money($record['gross_amount'] ?? 0).' incl. '.Format::money($record['excise_amount'] ?? 0).' excise'),
                TextColumn::make('wagered')
                    ->label('Staked towards unlocking')
                    ->alignEnd()
                    ->state(fn (array $record): string => static::stakedLabel($record)),
                TextColumn::make('locked_amount')
                    ->label('Still locked')
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn ($state): string => (float) $state > 0 ? 'warning' : 'gray')
                    ->formatStateUsing(fn ($state): string => Format::money($state)),
                TextColumn::make('unlocked')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state): string => $state ? 'success' : 'warning')
                    ->formatStateUsing(fn ($state): string => $state ? 'Unlocked' : 'Locked')
                    ->description(fn (array $record): ?string => filled($record['status'] ?? null) && $record['status'] !== 'granted'
                        ? ucfirst(str_replace('_', ' ', (string) $record['status']))
                        : null),
            ])
            ->paginated(false)
            ->emptyStateIcon('heroicon-o-ticket')
            ->emptyStateHeading(fn (): string => $this->apiError ? 'Could not be loaded from KadiApi' : 'No signup bonus')
            ->emptyStateDescription(fn (): ?string => $this->apiError ? 'Refresh the page to try again.' : 'This customer has not received a promotion bonus.');
    }

    public function placeholder(): View
    {
        return view('livewire.customer-referral-placeholder');
    }
}
