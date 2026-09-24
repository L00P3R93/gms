<?php

namespace App\Filament\Pages;

use App\Concerns\SettlesReferralWithdrawals;
use App\Filament\Resources\Accounts\AccountResource;
use App\Services\GameApiService;
use App\Support\ApiTablePaginator;
use App\Support\Format;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Referral wallet withdrawals to M-Pesa, read from KadiApi's
 * `/referral-withdrawals` endpoint. KadiApi pays them from the 4151665
 * shortcode; the GMS only lists them and lets finance settle the ones M-Pesa
 * never confirmed. Filters and pagination are forwarded to the API.
 */
class ReferralWithdrawalsPage extends Page implements HasTable
{
    use InteractsWithTable;
    use SettlesReferralWithdrawals;

    /**
     * Pending or processing withdrawals older than this are shown as stuck.
     */
    public const STUCK_AFTER_HOURS = 24;

    public const STATUSES = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Referral Withdrawals';

    protected static string|UnitEnum|null $navigationGroup = '🎁 Referrals';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'referral-withdrawals';

    protected ?string $heading = 'Referral Withdrawals';

    protected string $view = 'filament.pages.referral-withdrawals-page';

    public bool $apiError = false;

    /**
     * Bound to the URL so the dashboard can link straight to, for example, processing withdrawals.
     *
     * @var array<string, mixed>|null
     */
    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    /**
     * The list filtered to one status, e.g. for the reconciliation link to stuck withdrawals.
     */
    public static function getUrlForStatus(string $status): string
    {
        return static::getUrl(['filters' => ['status' => ['value' => $status]]]);
    }

    /**
     * Referral data is visible to whoever can view customers.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo('accounts.view') ?? false;
    }

    /**
     * @param  array<string, mixed>  $withdrawal
     */
    public static function isStuck(array $withdrawal): bool
    {
        if (! in_array($withdrawal['status'] ?? null, self::SETTLEABLE_STATUSES, true) || empty($withdrawal['created_at'])) {
            return false;
        }

        return Carbon::parse($withdrawal['created_at'])->lt(now()->subHours(self::STUCK_AFTER_HOURS));
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'processing' => 'info',
            'completed' => 'success',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    public static function customerUrl(int|string|null $customerId): ?string
    {
        if (blank($customerId) || ! AccountResource::canViewAny()) {
            return null;
        }

        return AccountResource::getUrl('view', ['record' => $customerId]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (array $filters, int|string $page, int|string $recordsPerPage): LengthAwarePaginator {
                $paginator = ApiTablePaginator::make(
                    response: $this->fetchWithdrawals($this->apiFilters($filters, (int) $page, (int) $recordsPerPage)),
                    page: $page,
                    perPage: $recordsPerPage,
                );

                // Key rows by withdrawal id so a row action can never land on a different
                // withdrawal if the list shifts between rendering and clicking.
                return $paginator->setCollection($paginator->getCollection()->keyBy('key'));
            })
            ->columns([
                TextColumn::make('created_at')
                    ->label('Requested')
                    ->formatStateUsing(fn ($state): string => Format::dateTime($state))
                    ->description(fn (array $record): ?string => static::isStuck($record) ? 'Stuck — over '.self::STUCK_AFTER_HOURS.'h' : null)
                    ->icon(fn (array $record): ?string => static::isStuck($record) ? 'heroicon-m-clock' : null)
                    ->iconColor('danger')
                    ->color(fn (array $record): ?string => static::isStuck($record) ? 'danger' : null),
                TextColumn::make('id')
                    ->label('Withdrawal')
                    ->formatStateUsing(fn ($state): string => '#'.$state),
                TextColumn::make('customer_id')
                    ->label('Customer')
                    ->formatStateUsing(fn ($state): string => '#'.$state)
                    ->color('primary')
                    ->url(fn (array $record): ?string => static::customerUrl($record['customer_id'] ?? null)),
                TextColumn::make('phone_no')
                    ->label('Phone'),
                TextColumn::make('amount')
                    ->alignEnd()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state): string => Format::money($state)),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => static::statusColor($state))
                    ->formatStateUsing(fn (?string $state): string => self::STATUSES[$state] ?? ucfirst((string) $state)),
                TextColumn::make('mpesa_receipt')
                    ->label('M-Pesa receipt')
                    ->placeholder('—')
                    ->copyable()
                    ->fontFamily('mono'),
                TextColumn::make('result_desc')
                    ->label('Result')
                    ->placeholder('—')
                    ->limit(40)
                    ->tooltip(fn (array $record): ?string => $record['result_desc'] ?? null),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(self::STATUSES),
                Filter::make('customer')
                    ->schema([
                        TextInput::make('customer_id')
                            ->label('Customer ID')
                            ->integer()
                            ->minValue(1),
                    ])
                    ->indicateUsing(fn (array $data): ?string => filled($data['customer_id'] ?? null) ? 'Customer #'.$data['customer_id'] : null),
            ])
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedEye)
                    ->color('info')
                    ->tooltip('View withdrawal')
                    ->url(fn (array $record): string => ReferralWithdrawalDetailPage::getUrl(['withdrawal' => $record['id']])),
                $this->settleReferralWithdrawalAction(),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateHeading(fn (): string => $this->apiError ? 'Referral withdrawals unavailable' : 'No referral withdrawals found')
            ->emptyStateDescription(fn (): string => $this->apiError
                ? 'KadiApi could not be reached. Refresh the page to try again.'
                : 'Try a different status or filter.')
            ->striped();
    }

    /**
     * Map the table's filter state onto the `/referral-withdrawals` query parameters.
     *
     * @param  array<string, array<string, mixed>>  $filters
     * @return array<string, mixed>
     */
    protected function apiFilters(array $filters, int $page, int $perPage): array
    {
        return [
            'status' => $filters['status']['value'] ?? null,
            'customer_id' => $filters['customer']['customer_id'] ?? null,
            'page' => max(1, $page),
            'per_page' => min(200, max(1, $perPage)),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function fetchWithdrawals(array $query): array
    {
        try {
            $response = app(GameApiService::class)->listReferralWithdrawals($query);
            $this->apiError = false;
        } catch (\Throwable $e) {
            Log::warning('Referral withdrawals list failed', ['error' => $e->getMessage()]);
            $this->apiError = true;

            return [];
        }

        $response['data'] = collect($response['data'] ?? [])
            ->filter(fn ($row): bool => is_array($row) && isset($row['id']))
            ->map(fn (array $row): array => ['key' => (string) $row['id'], ...$row])
            ->values()
            ->all();

        return $response;
    }

    /**
     * @param  array<string, mixed>  $withdrawal
     */
    protected function afterReferralWithdrawalSettled(array $withdrawal = []): void
    {
        $this->flushCachedTableRecords();
    }
}
