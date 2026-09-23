<?php

namespace App\Filament\Pages;

use App\Concerns\ClosesComplaints;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Widgets\ComplaintsStatsWidget;
use App\Services\GameApiService;
use App\Support\ApiTablePaginator;
use App\Support\Format;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Player complaints about games, tournaments and jackpots, read from the wallet
 * API's `/complaints` endpoint. Filters and pagination are forwarded to the API;
 * nothing is filtered in memory. Pending complaints can be closed from each row.
 */
class ComplaintsPage extends Page implements HasTable
{
    use ClosesComplaints;
    use InteractsWithTable;

    /**
     * Pending complaints older than this show as "aged disputes" in finance reconciliation.
     */
    public const AGED_AFTER_DAYS = 3;

    public const STATUSES = [
        'pending_dispute' => 'Pending',
        'resolved' => 'Resolved',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ];

    public const SUBJECT_TYPES = [
        'game' => 'Game',
        'tournament' => 'Tournament',
        'jackpot' => 'Jackpot',
    ];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Complaints';

    protected static string|UnitEnum|null $navigationGroup = '🎮 Players';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'complaints';

    protected ?string $heading = 'Complaints';

    protected string $view = 'filament.pages.complaints-page';

    public bool $apiError = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo('complaints.view') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = static::pendingCount();

        return $pending ? number_format($pending) : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Number of complaints still awaiting a decision, cached briefly because it renders on every page.
     */
    public static function pendingCount(): ?int
    {
        return static::countComplaints(['status' => 'pending_dispute']);
    }

    /**
     * Pending complaints filed at least {@see AGED_AFTER_DAYS} days ago.
     */
    public static function agedPendingCount(): ?int
    {
        return static::countComplaints([
            'status' => 'pending_dispute',
            'to' => today()->subDays(self::AGED_AFTER_DAYS)->toDateString(),
        ]);
    }

    /**
     * Drop the cached counts so the badge and stats reflect a complaint that was just closed.
     */
    public static function forgetComplaintCounts(): void
    {
        Cache::forget(static::countCacheKey(['status' => 'pending_dispute']));
        Cache::forget(static::countCacheKey(['status' => 'pending_dispute', 'to' => today()->subDays(self::AGED_AFTER_DAYS)->toDateString()]));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected static function countCacheKey(array $filters): string
    {
        return 'complaints_count_'.md5((string) json_encode($filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected static function countComplaints(array $filters): ?int
    {
        try {
            return Cache::remember(static::countCacheKey($filters), 60, fn (): int => (int) (
                app(GameApiService::class)->listComplaints([...$filters, 'per_page' => 1])['meta']['total'] ?? 0
            ));
        } catch (\Throwable $e) {
            Log::warning('Complaint count failed', ['filters' => $filters, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $complaint
     */
    public static function isAged(array $complaint): bool
    {
        if (($complaint['status'] ?? null) !== 'pending_dispute' || empty($complaint['created_at'])) {
            return false;
        }

        return Carbon::parse($complaint['created_at'])->lt(now()->subDays(self::AGED_AFTER_DAYS));
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'pending_dispute' => 'warning',
            'resolved' => 'success',
            'rejected' => 'danger',
            default => 'gray',
        };
    }

    public static function subjectColor(?string $subjectType): string
    {
        return match ($subjectType) {
            'game' => 'info',
            'tournament' => 'primary',
            'jackpot' => 'success',
            default => 'gray',
        };
    }

    /**
     * @return array<int, class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [ComplaintsStatsWidget::class];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (array $filters, int|string $page, int|string $recordsPerPage): LengthAwarePaginator {
                $paginator = ApiTablePaginator::make(
                    response: $this->fetchComplaints($this->apiFilters($filters, (int) $page, (int) $recordsPerPage)),
                    page: $page,
                    perPage: $recordsPerPage,
                );

                // Key rows by complaint id so a row action can never land on a different
                // complaint if the list shifts between rendering and clicking.
                return $paginator->setCollection($paginator->getCollection()->keyBy('key'));
            })
            ->columns([
                TextColumn::make('created_at')
                    ->label('Filed')
                    ->formatStateUsing(fn ($state): string => Format::dateTime($state))
                    ->description(fn (array $record): ?string => static::isAged($record) ? 'Aged dispute' : null)
                    ->icon(fn (array $record): ?string => static::isAged($record) ? 'heroicon-m-clock' : null)
                    ->iconColor('warning')
                    ->color(fn (array $record): ?string => static::isAged($record) ? 'warning' : null),
                TextColumn::make('complaint_id')
                    ->label('Complaint')
                    ->formatStateUsing(fn (?string $state): string => Str::limit((string) $state, 8, '…'))
                    ->tooltip(fn (array $record): ?string => $record['complaint_id'] ?? null)
                    ->copyable()
                    ->copyableState(fn (array $record): ?string => $record['complaint_id'] ?? null)
                    ->fontFamily('mono'),
                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->badge()
                    ->color(fn (?string $state): string => static::subjectColor($state))
                    ->formatStateUsing(fn (?string $state): string => self::SUBJECT_TYPES[$state] ?? ucfirst((string) $state)),
                TextColumn::make('customer_id')
                    ->label('Complainant')
                    ->formatStateUsing(fn ($state): string => '#'.$state)
                    ->color('primary')
                    ->url(fn (array $record): ?string => static::customerUrl($record['customer_id'] ?? null)),
                TextColumn::make('reason')
                    ->limit(40)
                    ->tooltip(fn (array $record): ?string => $record['reason'] ?? null)
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => static::statusColor($state))
                    ->formatStateUsing(fn (?string $state): string => self::STATUSES[$state] ?? ucfirst((string) $state)),
                TextColumn::make('disputed_amount')
                    ->label('Disputed')
                    ->alignEnd()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state): string => Format::money($state)),
                TextColumn::make('held_amount')
                    ->label('Held')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => Format::money($state)),
                TextColumn::make('shortfall_amount')
                    ->label('Shortfall')
                    ->alignEnd()
                    ->color(fn ($state): ?string => (float) $state > 0 ? 'danger' : 'gray')
                    ->formatStateUsing(fn ($state): string => Format::money($state)),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(self::STATUSES)
                    ->default('pending_dispute'),
                SelectFilter::make('subject_type')
                    ->label('Subject')
                    ->options(self::SUBJECT_TYPES),
                Filter::make('customer')
                    ->schema([
                        TextInput::make('customer_id')
                            ->label('Complainant customer ID')
                            ->integer()
                            ->minValue(1),
                    ])
                    ->indicateUsing(fn (array $data): ?string => filled($data['customer_id'] ?? null) ? 'Complainant #'.$data['customer_id'] : null),
                Filter::make('filed')
                    ->schema([
                        DatePicker::make('from')->label('Filed from'),
                        DatePicker::make('to')->label('Filed until'),
                    ])
                    ->indicateUsing(function (array $data): array {
                        return array_values(array_filter([
                            filled($data['from'] ?? null) ? 'Filed from '.Format::date($data['from']) : null,
                            filled($data['to'] ?? null) ? 'Filed until '.Format::date($data['to']) : null,
                        ]));
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedEye)
                    ->color('info')
                    ->tooltip('View complaint')
                    ->url(fn (array $record): string => ComplaintDetailPage::getUrl(['complaint' => $record['id']])),
                ActionGroup::make($this->closeComplaintActions())
                    ->tooltip('Close complaint'),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateIcon('heroicon-o-scale')
            ->emptyStateHeading(fn (): string => $this->apiError ? 'Complaints unavailable' : 'No complaints found')
            ->emptyStateDescription(fn (): string => $this->apiError
                ? 'The wallet API could not be reached. Refresh the page to try again.'
                : 'Try a different status or filter.')
            ->striped();
    }

    /**
     * Map the table's filter state onto the `/complaints` query parameters.
     *
     * @param  array<string, array<string, mixed>>  $filters
     * @return array<string, mixed>
     */
    protected function apiFilters(array $filters, int $page, int $perPage): array
    {
        return [
            'status' => $filters['status']['value'] ?? null,
            'subject_type' => $filters['subject_type']['value'] ?? null,
            'customer_id' => $filters['customer']['customer_id'] ?? null,
            'from' => $filters['filed']['from'] ?? null,
            'to' => $filters['filed']['to'] ?? null,
            'page' => max(1, $page),
            'per_page' => min(200, max(1, $perPage)),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function fetchComplaints(array $query): array
    {
        try {
            $response = app(GameApiService::class)->listComplaints($query);
            $this->apiError = false;
        } catch (\Throwable $e) {
            Log::warning('Complaints list failed', ['error' => $e->getMessage()]);
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

    public static function customerUrl(int|string|null $customerId): ?string
    {
        if (blank($customerId) || ! AccountResource::canViewAny()) {
            return null;
        }

        return AccountResource::getUrl('view', ['record' => $customerId]);
    }

    /**
     * @param  array<string, mixed>  $complaint
     */
    protected function afterComplaintClosed(array $complaint = []): void
    {
        static::forgetComplaintCounts();
        $this->flushCachedTableRecords();
    }
}
