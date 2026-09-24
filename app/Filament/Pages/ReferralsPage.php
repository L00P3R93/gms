<?php

namespace App\Filament\Pages;

use App\Services\GameApiService;
use App\Support\ApiTablePaginator;
use App\Support\Format;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use UnitEnum;

/**
 * Who referred whom in the player referral programme, read from KadiApi's
 * `/referrals` endpoint. Read-only: bonuses are paid by KadiApi when the
 * referred player is verified and when they first deposit. Filters and
 * pagination are forwarded to the API.
 */
class ReferralsPage extends Page implements HasTable
{
    use InteractsWithTable;

    public const STATUSES = [
        'pending_verification' => 'Pending verification',
        'verified' => 'Verified',
        'deposited' => 'Deposited',
    ];

    public const MILESTONES = [
        'signup' => 'Signup',
        'first_deposit' => 'First deposit',
    ];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationLabel = 'Referrals';

    protected static string|UnitEnum|null $navigationGroup = '🎁 Referrals';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'referrals';

    protected ?string $heading = 'Referrals';

    protected string $view = 'filament.pages.referrals-page';

    public bool $apiError = false;

    /**
     * Referral data is visible to whoever can view customers.
     */
    public static function canAccess(): bool
    {
        return ReferralWithdrawalsPage::canAccess();
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'pending_verification' => 'gray',
            'verified' => 'info',
            'deposited' => 'success',
            default => 'gray',
        };
    }

    /**
     * "Signup KES 10.00"-style labels for the bonuses a referral has paid.
     *
     * @param  array<string, mixed>  $referral
     * @return list<string>
     */
    public static function bonusLabels(array $referral): array
    {
        return collect($referral['bonuses'] ?? [])
            ->filter(fn ($bonus): bool => is_array($bonus))
            ->map(fn (array $bonus): string => (self::MILESTONES[$bonus['milestone'] ?? ''] ?? ucfirst(str_replace('_', ' ', (string) ($bonus['milestone'] ?? 'bonus'))))
                .' '.Format::money($bonus['amount'] ?? 0))
            ->values()
            ->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (array $filters, int|string $page, int|string $recordsPerPage): LengthAwarePaginator => ApiTablePaginator::make(
                response: $this->fetchReferrals($this->apiFilters($filters, (int) $page, (int) $recordsPerPage)),
                page: $page,
                perPage: $recordsPerPage,
            ))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Signed up')
                    ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
                TextColumn::make('referrer_id')
                    ->label('Referrer')
                    ->formatStateUsing(fn ($state): string => '#'.$state)
                    ->color('primary')
                    ->url(fn (array $record): ?string => ReferralWithdrawalsPage::customerUrl($record['referrer_id'] ?? null)),
                TextColumn::make('code_used')
                    ->label('Code')
                    ->fontFamily('mono')
                    ->copyable(),
                TextColumn::make('referred_name')
                    ->label('Referred player')
                    ->description(fn (array $record): string => '#'.($record['referred_id'] ?? '—').' · '.($record['referred_phone'] ?? '—'))
                    ->color('primary')
                    ->url(fn (array $record): ?string => ReferralWithdrawalsPage::customerUrl($record['referred_id'] ?? null)),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => static::statusColor($state))
                    ->formatStateUsing(fn (?string $state): string => self::STATUSES[$state] ?? ucfirst((string) $state)),
                TextColumn::make('verified_at')
                    ->label('Verified')
                    ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
                TextColumn::make('first_deposited_at')
                    ->label('First deposit')
                    ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
                TextColumn::make('bonuses')
                    ->state(fn (array $record): array => static::bonusLabels($record))
                    ->badge()
                    ->color('success')
                    ->placeholder('None yet'),
                TextColumn::make('earned')
                    ->alignEnd()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state): string => Format::money($state)),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(self::STATUSES),
                Filter::make('signed_up')
                    ->schema([
                        DatePicker::make('from')->label('Signed up from'),
                        DatePicker::make('to')->label('Signed up until'),
                    ])
                    ->indicateUsing(function (array $data): array {
                        return array_values(array_filter([
                            filled($data['from'] ?? null) ? 'Signed up from '.Format::date($data['from']) : null,
                            filled($data['to'] ?? null) ? 'Signed up until '.Format::date($data['to']) : null,
                        ]));
                    }),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateIcon('heroicon-o-user-plus')
            ->emptyStateHeading(fn (): string => $this->apiError ? 'Referrals unavailable' : 'No referrals found')
            ->emptyStateDescription(fn (): string => $this->apiError
                ? 'KadiApi could not be reached. Refresh the page to try again.'
                : 'Try a different status or date range.')
            ->striped();
    }

    /**
     * Map the table's filter state onto the `/referrals` query parameters.
     *
     * @param  array<string, array<string, mixed>>  $filters
     * @return array<string, mixed>
     */
    protected function apiFilters(array $filters, int $page, int $perPage): array
    {
        return [
            'status' => $filters['status']['value'] ?? null,
            'from' => $filters['signed_up']['from'] ?? null,
            'to' => $filters['signed_up']['to'] ?? null,
            'page' => max(1, $page),
            'per_page' => min(200, max(1, $perPage)),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function fetchReferrals(array $query): array
    {
        try {
            $response = app(GameApiService::class)->listReferrals($query);
            $this->apiError = false;
        } catch (\Throwable $e) {
            Log::warning('Referrals list failed', ['error' => $e->getMessage()]);
            $this->apiError = true;

            return [];
        }

        $response['data'] = collect($response['data'] ?? [])
            ->filter(fn ($row): bool => is_array($row) && isset($row['id']))
            ->values()
            ->all();

        return $response;
    }
}
