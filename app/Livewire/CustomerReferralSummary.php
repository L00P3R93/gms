<?php

namespace App\Livewire;

use App\Exceptions\GameApiException;
use App\Filament\Pages\ReferralsPage;
use App\Filament\Pages\ReferralWithdrawalsPage;
use App\Services\GameApiService;
use App\Support\ApiAuditLog;
use App\Support\Format;
use App\Support\ReferralCode;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * The top of a customer's Referrals tab: their own code, link and QR code, who
 * referred them, and their referral counts, earnings and wallet balance. Each
 * part is fetched separately so one failing KadiApi call does not hide the rest.
 * Mounted lazily, so nothing is fetched until the tab is opened.
 */
class CustomerReferralSummary extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public int $customerId;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $referralCode = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $referrer = null;

    /**
     * @var array<string, mixed>
     */
    public array $stats = [];

    /**
     * The parts that could not be loaded: code, referrer, stats.
     *
     * @var list<string>
     */
    public array $failed = [];

    public function mount(int $customerId): void
    {
        $this->customerId = $customerId;
        $this->loadReferralData();
    }

    protected function loadReferralData(): void
    {
        $gameApi = app(GameApiService::class);
        $this->failed = [];

        $this->referralCode = $this->attempt('code', fn (): ?array => $gameApi->getCustomerReferralCode($this->customerId));
        $this->referrer = $this->attempt('referrer', fn (): ?array => $gameApi->getCustomerReferrer($this->customerId));
        $this->stats = $this->attempt('stats', fn (): array => $gameApi->getCustomerReferralStats($this->customerId)) ?? [];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fetch
     * @return T|null
     */
    protected function attempt(string $part, callable $fetch): mixed
    {
        try {
            return $fetch();
        } catch (\Throwable $e) {
            Log::warning('Customer referral lookup failed', ['customer' => $this->customerId, 'part' => $part, 'error' => $e->getMessage()]);
            $this->failed[] = $part;

            return null;
        }
    }

    /**
     * The QR code as an image source, or null. KadiApi stores a URL or a data URI; anything
     * else (for example a `javascript:` string) is not rendered.
     */
    public static function qrCodeSource(?string $qrCode): ?string
    {
        if (blank($qrCode)) {
            return null;
        }

        if (preg_match('#^data:image/(png|jpe?g|gif|webp|svg\+xml);base64,[A-Za-z0-9+/=\s]+$#', $qrCode) === 1) {
            return $qrCode;
        }

        return preg_match('#^https?://#i', $qrCode) === 1 && filter_var($qrCode, FILTER_VALIDATE_URL) ? $qrCode : null;
    }

    public function summaryInfolist(Schema $schema): Schema
    {
        $unavailable = 'Could not be loaded from KadiApi. Refresh the page to try again.';

        return $schema
            ->constantState(fn (): array => $this->infolistState())
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Section::make('Referral code')
                    ->columnSpan(1)
                    ->afterHeader([$this->changeReferralCodeAction()])
                    ->schema(fn (): array => match (true) {
                        in_array('code', $this->failed, true) => [TextEntry::make('code_unavailable')->hiddenLabel()->state($unavailable)->color('warning')],
                        $this->referralCode === null => [TextEntry::make('no_code')->hiddenLabel()->state('No referral code yet.')->color('gray')],
                        default => [
                            TextEntry::make('code.code')
                                ->label('Code')
                                ->fontFamily('mono')
                                ->weight('bold')
                                ->copyable(),
                            TextEntry::make('code.link')
                                ->label('Link')
                                ->placeholder('—')
                                ->copyable()
                                ->url(fn (?string $state): ?string => filled($state) && preg_match('#^https?://#i', $state) === 1 ? $state : null, shouldOpenInNewTab: true),
                            ViewEntry::make('code.qr_source')
                                ->label('QR code')
                                ->view('infolists.referral-qr-code'),
                        ],
                    }),
                Section::make('Referred by')
                    ->columnSpan(1)
                    ->schema(fn (): array => match (true) {
                        in_array('referrer', $this->failed, true) => [TextEntry::make('referrer_unavailable')->hiddenLabel()->state($unavailable)->color('warning')],
                        $this->referrer === null => [TextEntry::make('not_referred')->hiddenLabel()->state('Not referred by another player.')->color('gray')],
                        default => [
                            TextEntry::make('referrer.referrer_id')
                                ->label('Referrer')
                                ->formatStateUsing(fn ($state): string => 'Customer #'.$state)
                                ->color('primary')
                                ->url(fn ($state): ?string => ReferralWithdrawalsPage::customerUrl($state)),
                            TextEntry::make('referrer.code_used')
                                ->label('Code used')
                                ->fontFamily('mono'),
                            TextEntry::make('referrer.status')
                                ->label('Status')
                                ->badge()
                                ->color(fn (?string $state): string => ReferralsPage::statusColor($state))
                                ->formatStateUsing(fn (?string $state): string => ReferralsPage::STATUSES[$state] ?? ucfirst((string) $state)),
                            TextEntry::make('referrer.created_at')
                                ->label('Signed up')
                                ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
                        ],
                    }),
                Section::make('Referral earnings')
                    ->columnSpan(1)
                    ->columns(2)
                    ->schema(fn (): array => in_array('stats', $this->failed, true)
                        ? [TextEntry::make('stats_unavailable')->hiddenLabel()->state($unavailable)->color('warning')->columnSpanFull()]
                        : [
                            TextEntry::make('stats.wallet_balance')
                                ->label('Wallet balance')
                                ->weight('bold')
                                ->color('success')
                                ->formatStateUsing(fn ($state): string => Format::money($state)),
                            TextEntry::make('stats.earned.total')
                                ->label('Earned in total')
                                ->formatStateUsing(fn ($state): string => Format::money($state)),
                            TextEntry::make('stats.earned.signup')
                                ->label('Signup bonuses')
                                ->formatStateUsing(fn ($state): string => Format::money($state)),
                            TextEntry::make('stats.earned.first_deposit')
                                ->label('First-deposit bonuses')
                                ->formatStateUsing(fn ($state): string => Format::money($state)),
                            TextEntry::make('stats.referrals.total')
                                ->label('Referrals')
                                ->numeric(),
                            TextEntry::make('stats.referrals.this_month')
                                ->label('This month')
                                ->numeric(),
                            TextEntry::make('stats.referrals.verified')
                                ->label('Verified')
                                ->numeric(),
                            TextEntry::make('stats.referrals.deposited')
                                ->label('Deposited')
                                ->numeric(),
                            TextEntry::make('stats.referrals.pending_verification')
                                ->label('Pending verification')
                                ->numeric(),
                        ]),
            ]);
    }

    public static function canEditReferralCodes(): bool
    {
        return auth()->user()?->hasPermissionTo('referral-codes.edit') ?? false;
    }

    /**
     * Set or change the customer's code. The GMS rebuilds the link and QR code the way the
     * player app does, so all three stay in step. A 409 (code taken) and KadiApi's 422 errors
     * land on the code field; every attempt is written to the audit log.
     */
    public function changeReferralCodeAction(): Action
    {
        return Action::make('changeReferralCode')
            ->label(fn (): string => $this->referralCode === null ? 'Set code' : 'Change code')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->size('sm')
            ->visible(fn (): bool => static::canEditReferralCodes() && ! in_array('code', $this->failed, true))
            ->modalHeading(fn (): string => $this->referralCode === null ? 'Set referral code' : 'Change referral code')
            ->modalDescription('The link and QR code are rebuilt from the new code. Earlier referrals and earnings are kept, but the old code, link and QR code stop working for new signups.')
            ->modalSubmitActionLabel('Save code')
            // fillForm() replaces the form's defaults, so the Idempotency-Key is generated here.
            ->fillForm(fn (): array => [
                'idempotency_key' => Str::uuid()->toString(),
                'code' => $this->referralCode['code'] ?? null,
            ])
            ->schema([
                Hidden::make('idempotency_key'),
                TextInput::make('code')
                    ->label('Referral code')
                    ->required()
                    ->regex(ReferralCode::PATTERN)
                    ->validationMessages(['regex' => 'The code must be 4 to 20 letters or digits.'])
                    ->live(onBlur: true)
                    ->helperText(fn (?string $state): string => 'Link: '.ReferralCode::link(filled($state) ? $state : '…'))
                    ->dehydrateStateUsing(fn (?string $state): string => ReferralCode::normalize((string) $state)),
            ])
            ->action(function (array $data, Action $action): void {
                $code = $data['code'];
                $link = ReferralCode::link($code);
                $qrCode = ReferralCode::qrCodeDataUri($link);
                $auditPayload = ['code' => $code, 'link' => $link, 'qr_code' => 'PNG data URI, '.strlen($qrCode).' chars', 'idempotency_key' => $data['idempotency_key']];

                try {
                    $saved = app(GameApiService::class)->updateCustomerReferralCode($this->customerId, $code, $link, $qrCode, $data['idempotency_key']);
                } catch (GameApiException $e) {
                    ApiAuditLog::record('ReferralCode', $this->customerId, 'update_failed', $auditPayload, $e->statusCode, [
                        'message' => $e->apiMessage !== '' ? $e->apiMessage : $e->getMessage(),
                        'errors' => $e->errors,
                    ]);

                    $this->handleChangeCodeFailure($e, $action);

                    return;
                }

                ApiAuditLog::record('ReferralCode', $this->customerId, 'updated', $auditPayload, 200, Arr::except($saved, ['qr_code']));

                Notification::make()
                    ->title('Referral code saved')
                    ->body('The code is now '.($saved['code'] ?? $code).'.')
                    ->success()
                    ->send();

                $this->referralCode = $saved !== [] ? $saved : ['code' => $code, 'link' => $link, 'qr_code' => $qrCode];
                unset($this->cachedSchemas['summaryInfolist']);
            });
    }

    /**
     * A taken code (409) and validation errors (422) go on the code field with a fresh
     * Idempotency-Key, because the corrected code is a new request. Rate limits, outages and
     * server errors keep the modal open for a retry with the same key.
     *
     * @throws ValidationException
     */
    protected function handleChangeCodeFailure(GameApiException $e, Action $action): void
    {
        $message = $e->apiMessage !== '' ? $e->apiMessage : $e->getMessage();
        $statePath = "mountedActions.{$action->getNestingIndex()}.data";

        if (in_array($e->statusCode, [409, 422], true)) {
            $this->mountedActions[$action->getNestingIndex()]['data']['idempotency_key'] = Str::uuid()->toString();

            $codeErrors = $e->statusCode === 409 ? [$message] : Arr::wrap($e->errors['code'] ?? []);

            if ($codeErrors !== []) {
                throw ValidationException::withMessages(["{$statePath}.code" => $codeErrors]);
            }
        }

        Notification::make()
            ->title('Could not save the referral code')
            ->body($message)
            ->danger()
            ->send();

        if (in_array($e->statusCode, [0, 422, 429], true) || $e->statusCode >= 500) {
            $action->halt();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function infolistState(): array
    {
        return [
            'code' => [
                ...($this->referralCode ?? []),
                'qr_source' => static::qrCodeSource($this->referralCode['qr_code'] ?? null),
            ],
            'referrer' => $this->referrer ?? [],
            'stats' => $this->stats,
        ];
    }

    public function placeholder(): View
    {
        return view('livewire.customer-referral-placeholder');
    }

    public function render(): View
    {
        return view('livewire.customer-referral-summary');
    }
}
