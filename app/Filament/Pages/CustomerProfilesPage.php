<?php

namespace App\Filament\Pages;

use App\Models\Account;
use App\Models\CustomerPicViolation;
use App\Models\User;
use App\Services\BrevoEmailService;
use App\Services\GameApiService;
use App\Support\ApiTablePaginator;
use App\Support\Format;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class CustomerProfilesPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Customer Profiles';

    protected static string|UnitEnum|null $navigationGroup = '🎮 Players';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.customer-profiles-page';

    public bool $apiError = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo('accounts.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (int|string $page, int|string $recordsPerPage, ?string $search, ?string $sortColumn, ?string $sortDirection): LengthAwarePaginator => ApiTablePaginator::make(
                response: $this->fetchRecords(),
                page: $page,
                perPage: $recordsPerPage,
                search: $search,
                searchKeys: ['name', 'email', 'phone_no'],
                sortColumn: $sortColumn ?? 'id',
                sortDirection: $sortDirection ?? 'desc',
            ))
            ->columns([
                Split::make([
                    ImageColumn::make('avatar_url')
                        ->label('')
                        ->circular()
                        ->size(48)
                        ->grow(false),
                    Stack::make([
                        TextColumn::make('name')
                            ->searchable()
                            ->sortable()
                            ->weight('bold'),
                        TextColumn::make('phone_no')
                            ->label('Phone')
                            ->searchable()
                            ->formatStateUsing(fn ($state): string => Format::maskedPhone($state))
                            ->color('gray')
                            ->size(TextSize::Small),
                    ]),
                    Stack::make([
                        TextColumn::make('email')
                            ->searchable()
                            ->color('gray')
                            ->size(TextSize::Small),
                        TextColumn::make('status')
                            ->badge()
                            ->formatStateUsing(fn ($state): string => match ((int) $state) {
                                Account::STATUS_ACTIVE => 'Active',
                                3 => 'Banned',
                                default => 'Hidden',
                            })
                            ->color(fn ($state): string => match ((int) $state) {
                                Account::STATUS_ACTIVE => 'success',
                                3 => 'danger',
                                default => 'gray',
                            }),
                    ])->visibleFrom('md'),
                    Stack::make([
                        TextColumn::make('violation_count')
                            ->label('Strikes')
                            ->badge()
                            ->formatStateUsing(fn ($state): string => ($state ?? 0).' / 3')
                            ->color(fn ($state): string => match (true) {
                                ($state ?? 0) >= 3 => 'danger',
                                ($state ?? 0) >= 2 => 'warning',
                                ($state ?? 0) >= 1 => 'info',
                                default => 'gray',
                            })
                            ->size(TextSize::Small),
                    ])->visibleFrom('md'),
                ])->from('md'),
            ])
            ->recordActions([
                Action::make('reset_pic')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPhoto)
                    ->color('warning')
                    ->tooltip('Reset Profile Picture')
                    ->requiresConfirmation()
                    ->modalHeading('Reset Profile Picture')
                    ->modalDescription(fn (array $record): string => "Remove {$record['name']}'s profile picture? They will be notified by email. This counts as a strike (max 3 before auto-ban).")
                    ->action(fn (array $record): mixed => $this->resetProfilePic($record))
                    ->visible(fn (array $record): bool => auth()->user()->hasPermissionTo('accounts.view')
                        && ($record['pic'] ?? 'profilepic.png') !== 'profilepic.png'),

                Action::make('ban')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->tooltip('Ban Customer')
                    ->requiresConfirmation()
                    ->modalHeading('Ban Customer')
                    ->modalDescription(fn (array $record): string => "Suspend {$record['name']}'s account? This will prevent them from accessing the platform.")
                    ->action(fn (array $record): mixed => $this->banCustomer($record))
                    ->visible(fn (array $record): bool => auth()->user()->hasPermissionTo('accounts.ban')
                        && (int) ($record['status'] ?? 0) !== 3),
            ])
            ->emptyStateIcon('heroicon-o-photo')
            ->emptyStateHeading(fn (): string => $this->apiError ? 'Customers unavailable' : 'No customers found')
            ->emptyStateDescription(fn (): string => $this->apiError
                ? 'The wallet API could not be reached. Refresh the page to try again.'
                : 'No customers have registered yet.')
            ->striped();
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function fetchRecords(): array
    {
        try {
            $customers = Cache::remember('customers_list', 300, fn (): array => app(GameApiService::class)->listCustomers());
            $this->apiError = false;

            $customers = collect($customers)
                ->filter(fn ($item): bool => is_array($item) && isset($item['id']))
                ->values()
                ->all();

            $customerIds = array_column($customers, 'id');

            $kadiPics = $customerIds !== []
                ? Cache::remember('customer_pics', 300, fn (): array => DB::connection('kadi')
                    ->table('accounts')
                    ->whereIn('id', $customerIds)
                    ->select(['id', 'pic'])
                    ->get()
                    ->pluck('pic', 'id')
                    ->all())
                : [];

            $violations = $customerIds !== []
                ? CustomerPicViolation::whereIn('customer_id', $customerIds)
                    ->pluck('violation_count', 'customer_id')
                    ->all()
                : [];

            $base = rtrim(config('services.game_api.image_url'), '/');

            return array_map(function (array $customer) use ($kadiPics, $base, $violations): array {
                $pic = $kadiPics[$customer['id']] ?? 'profilepic.png';
                $customer['pic'] = $pic;
                $customer['avatar_url'] = $pic === 'profilepic.png'
                    ? asset('images/avatar.png')
                    : "{$base}{$pic}";
                $customer['violation_count'] = $violations[$customer['id']] ?? 0;

                return $customer;
            }, $customers);
        } catch (\Throwable) {
            $this->apiError = true;

            return [];
        }
    }

    private function resetProfilePic(array $record): void
    {
        $customerId = $record['id'];
        $customerName = $record['name'] ?? 'Customer';
        $customerEmail = $record['email'] ?? null;

        DB::connection('kadi')
            ->table('accounts')
            ->where('id', $customerId)
            ->update(['pic' => 'profilepic.png']);

        Cache::forget('customer_pics');

        $violation = CustomerPicViolation::firstOrCreate(
            ['customer_id' => $customerId],
            ['customer_name' => $customerName, 'customer_email' => $customerEmail, 'violation_count' => 0],
        );

        $violation->increment('violation_count');
        $violation->refresh();

        $strikeCount = $violation->violation_count;
        $autoBanned = $strikeCount >= 3;

        if ($autoBanned) {
            $this->executeBan($record, notifyEmail: false);
        }

        if ($customerEmail) {
            app(BrevoEmailService::class)->sendProfilePicViolationEmail(
                toEmail: $customerEmail,
                toName: $customerName,
                strikeCount: $strikeCount,
                autoBanned: $autoBanned,
            );
        }

        $staff = User::role(['super-admin', 'admin'])->get();

        $notif = Notification::make()
            ->title("Profile pic reset — {$customerName}")
            ->body("Strike {$strikeCount}/3".($autoBanned ? ' · Account auto-banned' : ''))
            ->warning();

        $notif->send();
        $notif->sendToDatabase($staff);

        if ($autoBanned) {
            Notification::make()
                ->title("Customer auto-banned — {$customerName}")
                ->body('Reached 3 profile picture strikes')
                ->danger()
                ->sendToDatabase($staff);
        }
    }

    private function banCustomer(array $record): void
    {
        $customerId = $record['id'];
        $customerName = $record['name'] ?? 'Customer';
        $customerEmail = $record['email'] ?? null;

        $this->executeBan($record, notifyEmail: true);

        Cache::forget('customers_list');

        $staff = User::role(['super-admin', 'admin'])->get();

        $notif = Notification::make()
            ->title("Customer banned — {$customerName}")
            ->body('Banned by '.auth()->user()->name)
            ->danger();

        $notif->send();
        $notif->sendToDatabase($staff);
    }

    private function executeBan(array $record, bool $notifyEmail): void
    {
        $customerName = $record['name'] ?? 'Customer';
        $customerEmail = $record['email'] ?? null;

        app(GameApiService::class)->updateCustomer($record['id'], ['status' => 3]);

        Cache::forget('customers_list');

        if ($notifyEmail && $customerEmail) {
            app(BrevoEmailService::class)->sendCustomerBannedEmail(
                toEmail: $customerEmail,
                toName: $customerName,
            );
        }
    }
}
