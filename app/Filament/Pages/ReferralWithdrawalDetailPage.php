<?php

namespace App\Filament\Pages;

use App\Concerns\SettlesReferralWithdrawals;
use App\Exceptions\GameApiException;
use App\Services\GameApiService;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Url;

/**
 * One referral withdrawal from KadiApi: the payout, what M-Pesa reported and,
 * once settled by hand, who settled it and why. Pending or processing
 * withdrawals can be settled from the header.
 */
class ReferralWithdrawalDetailPage extends Page
{
    use SettlesReferralWithdrawals;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'referral-withdrawals/detail';

    protected string $view = 'filament.pages.referral-withdrawal-detail-page';

    #[Url]
    public ?int $withdrawal = null;

    /**
     * @var array<string, mixed>
     */
    public array $details = [];

    public bool $apiError = false;

    public static function canAccess(): bool
    {
        return ReferralWithdrawalsPage::canAccess();
    }

    public function mount(): void
    {
        abort_if($this->withdrawal === null, 404);

        $this->loadWithdrawal();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->withdrawal ? 'Referral withdrawal #'.$this->withdrawal : 'Referral withdrawal';
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [ReferralWithdrawalsPage::getUrl() => 'Referral Withdrawals', 'Withdrawal'];
    }

    protected function loadWithdrawal(): void
    {
        try {
            $this->details = app(GameApiService::class)->getReferralWithdrawal($this->withdrawal);
            $this->apiError = false;
        } catch (GameApiException $e) {
            abort_if($e->statusCode === 404, 404);

            Log::warning('Referral withdrawal lookup failed', ['withdrawal' => $this->withdrawal, 'error' => $e->getMessage()]);
            $this->apiError = true;
        }
    }

    /**
     * @param  array<string, mixed>|null  $record
     * @return array<string, mixed>
     */
    protected function withdrawalForAction(?array $record): array
    {
        return $this->details;
    }

    /**
     * @param  array<string, mixed>  $withdrawal
     */
    protected function afterReferralWithdrawalSettled(array $withdrawal = []): void
    {
        if ($withdrawal !== []) {
            $this->details = $withdrawal;
        } else {
            $this->loadWithdrawal();
        }

        // The infolist was built from the old withdrawal earlier in this request.
        unset($this->cachedSchemas['withdrawalInfolist']);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [$this->settleReferralWithdrawalAction()];
    }

    public function withdrawalInfolist(Schema $schema): Schema
    {
        return $schema
            ->constantState(fn (): array => $this->details)
            ->components([
                Section::make('Withdrawal')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('amount')
                            ->weight('bold')
                            ->formatStateUsing(fn ($state): string => Format::money($state)),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (?string $state): string => ReferralWithdrawalsPage::statusColor($state))
                            ->formatStateUsing(fn (?string $state): string => ReferralWithdrawalsPage::STATUSES[$state] ?? ucfirst((string) $state))
                            ->helperText(fn (): ?string => ReferralWithdrawalsPage::isStuck($this->details)
                                ? 'Stuck — no M-Pesa result for over '.ReferralWithdrawalsPage::STUCK_AFTER_HOURS.' hours'
                                : null),
                        TextEntry::make('created_at')
                            ->label('Requested')
                            ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
                        TextEntry::make('customer_id')
                            ->label('Customer')
                            ->formatStateUsing(fn ($state): string => '#'.$state)
                            ->color('primary')
                            ->url(fn (): ?string => ReferralWithdrawalsPage::customerUrl($this->details['customer_id'] ?? null)),
                        TextEntry::make('phone_no')
                            ->label('Phone'),
                    ]),
                Section::make('M-Pesa result')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('mpesa_receipt')
                            ->label('Receipt')
                            ->placeholder('—')
                            ->copyable()
                            ->fontFamily('mono'),
                        TextEntry::make('result_code')
                            ->label('Result code')
                            ->placeholder('—'),
                        TextEntry::make('result_desc')
                            ->label('Result')
                            ->placeholder('—'),
                        TextEntry::make('completed_at')
                            ->label('Completed')
                            ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
                        TextEntry::make('failed_at')
                            ->label('Failed')
                            ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
                    ]),
                Section::make('Manual settlement')
                    ->visible(fn (): bool => filled($this->details['settled_by'] ?? null) || filled($this->details['settlement_note'] ?? null))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('settlement_note')
                            ->label('Note')
                            ->columnSpanFull(),
                        TextEntry::make('settled_by')
                            ->label('Settled by'),
                    ]),
            ]);
    }
}
