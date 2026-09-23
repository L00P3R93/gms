<?php

namespace App\Filament\Pages;

use App\Concerns\ClosesComplaints;
use App\Exceptions\GameApiException;
use App\Services\GameApiService;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Url;

/**
 * One complaint from the wallet API: what was disputed, what money is on hold
 * and, once closed, how it was settled. Pending complaints can be resolved,
 * rejected or cancelled from the header.
 */
class ComplaintDetailPage extends Page
{
    use ClosesComplaints;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'complaints/detail';

    protected string $view = 'filament.pages.complaint-detail-page';

    #[Url]
    public ?int $complaint = null;

    /**
     * @var array<string, mixed>
     */
    public array $details = [];

    public bool $apiError = false;

    public static function canAccess(): bool
    {
        return ComplaintsPage::canAccess();
    }

    public function mount(): void
    {
        abort_if($this->complaint === null, 404);

        $this->loadComplaint();
    }

    public function getTitle(): string|Htmlable
    {
        $reference = $this->details['complaint_id'] ?? null;

        return $reference ? 'Complaint '.substr($reference, 0, 8) : 'Complaint';
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [ComplaintsPage::getUrl() => 'Complaints', 'Complaint'];
    }

    protected function loadComplaint(): void
    {
        try {
            $this->details = app(GameApiService::class)->getComplaint($this->complaint);
            $this->apiError = false;
        } catch (GameApiException $e) {
            abort_if($e->statusCode === 404, 404);

            Log::warning('Complaint lookup failed', ['complaint' => $this->complaint, 'error' => $e->getMessage()]);
            $this->apiError = true;
        }
    }

    /**
     * @param  array<string, mixed>|null  $record
     * @return array<string, mixed>
     */
    protected function complaintForAction(?array $record): array
    {
        return $this->details;
    }

    /**
     * @param  array<string, mixed>  $complaint
     */
    protected function afterComplaintClosed(array $complaint = []): void
    {
        ComplaintsPage::forgetComplaintCounts();

        if ($complaint !== []) {
            $this->details = $complaint;
        } else {
            $this->loadComplaint();
        }

        // The infolist was built from the old complaint earlier in this request.
        unset($this->cachedSchemas['complaintInfolist']);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return $this->closeComplaintActions();
    }

    public function complaintInfolist(Schema $schema): Schema
    {
        return $schema
            ->constantState(fn (): array => $this->infolistState())
            ->components([
                Section::make('Complaint')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('complaint_id')
                            ->label('Complaint ID')
                            ->copyable()
                            ->fontFamily('mono'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (?string $state): string => ComplaintsPage::statusColor($state))
                            ->formatStateUsing(fn (?string $state): string => ComplaintsPage::STATUSES[$state] ?? ucfirst((string) $state)),
                        TextEntry::make('created_at')
                            ->label('Filed')
                            ->formatStateUsing(fn ($state): string => Format::dateTime($state))
                            ->helperText(fn (): ?string => ComplaintsPage::isAged($this->details) ? 'Aged dispute — pending for over '.ComplaintsPage::AGED_AFTER_DAYS.' days' : null),
                        TextEntry::make('subject_type')
                            ->label('Subject')
                            ->badge()
                            ->color(fn (?string $state): string => ComplaintsPage::subjectColor($state))
                            ->formatStateUsing(fn (?string $state): string => ComplaintsPage::SUBJECT_TYPES[$state] ?? ucfirst((string) $state)),
                        TextEntry::make('subject_reference')
                            ->label('Subject ID'),
                        TextEntry::make('complainant')
                            ->color('primary')
                            ->url(fn (): ?string => ComplaintsPage::customerUrl($this->details['customer_id'] ?? null)),
                        TextEntry::make('reason')
                            ->columnSpanFull(),
                        TextEntry::make('description')
                            ->placeholder('No description given.')
                            ->columnSpanFull(),
                        TextEntry::make('filed_by')
                            ->label('Filed by'),
                    ]),
                Section::make('Money')
                    ->description('Disputed is what the complainant claims; held is what was actually taken into escrow; shortfall is what the winner had already spent.')
                    ->columns(['default' => 2, 'lg' => 3])
                    ->schema([
                        $this->moneyEntry('disputed_amount', 'Disputed'),
                        $this->moneyEntry('held_amount', 'Held'),
                        $this->moneyEntry('shortfall_amount', 'Shortfall')
                            ->color(fn ($state): ?string => (float) $state > 0 ? 'danger' : null),
                        $this->moneyEntry('refunded_amount', 'Refunded'),
                        $this->moneyEntry('house_cuts_reversed', 'House cuts reversed'),
                        $this->moneyEntry('released_amount', 'Released'),
                    ]),
                Section::make('Disputed transactions')
                    ->schema([
                        RepeatableEntry::make('disputed_transactions')
                            ->hiddenLabel()
                            ->placeholder('No transactions attached.')
                            ->table([
                                TableColumn::make('Transaction'),
                                TableColumn::make('Winner'),
                                TableColumn::make('Source wallet'),
                                TableColumn::make('Amount'),
                                TableColumn::make('Held'),
                                TableColumn::make('Shortfall'),
                                TableColumn::make('Balance'),
                                TableColumn::make('Status'),
                            ])
                            ->schema([
                                TextEntry::make('transaction'),
                                TextEntry::make('winner')
                                    ->color('primary')
                                    ->url(fn (Get $get): ?string => ComplaintsPage::customerUrl($get('customer_id'))),
                                TextEntry::make('source_wallet'),
                                TextEntry::make('amount')->formatStateUsing(fn ($state): string => Format::money($state)),
                                TextEntry::make('held_amount')->formatStateUsing(fn ($state): string => Format::money($state)),
                                TextEntry::make('shortfall_amount')->formatStateUsing(fn ($state): string => Format::money($state)),
                                TextEntry::make('balance')->formatStateUsing(fn ($state): string => Format::money($state)),
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        'held' => 'warning',
                                        'reversed' => 'success',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state)),
                            ]),
                    ]),
                Section::make('Resolution')
                    ->visible(fn (): bool => ($this->details['status'] ?? 'pending_dispute') !== 'pending_dispute')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('resolution_note')
                            ->label('Note')
                            ->columnSpanFull(),
                        TextEntry::make('closed_by')
                            ->label('Closed by'),
                        TextEntry::make('closed_at')
                            ->label('Closed')
                            ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
                        RepeatableEntry::make('refunds')
                            ->label('Refunds')
                            ->placeholder('No refunds — the held money went back to the winner.')
                            ->columnSpanFull()
                            ->table([
                                TableColumn::make('Customer'),
                                TableColumn::make('Wallet'),
                                TableColumn::make('Amount'),
                            ])
                            ->schema([
                                TextEntry::make('customer')
                                    ->color('primary')
                                    ->url(fn (Get $get): ?string => ComplaintsPage::customerUrl($get('customer_id'))),
                                TextEntry::make('wallet_id')->formatStateUsing(fn ($state): string => '#'.$state),
                                TextEntry::make('amount')->formatStateUsing(fn ($state): string => Format::money($state)),
                            ]),
                    ]),
            ]);
    }

    protected function moneyEntry(string $name, string $label): TextEntry
    {
        return TextEntry::make($name)
            ->label($label)
            ->weight('bold')
            ->formatStateUsing(fn ($state): string => Format::money($state));
    }

    /**
     * The complaint plus display values for the subject and the people involved.
     *
     * @return array<string, mixed>
     */
    protected function infolistState(): array
    {
        $complaint = $this->details;

        $complaint['subject_reference'] = match (true) {
            filled($complaint['game_wallet_id'] ?? null) => 'Game wallet #'.$complaint['game_wallet_id'],
            filled($complaint['competition_wallet_id'] ?? null) => 'Competition wallet #'.$complaint['competition_wallet_id'],
            default => '—',
        };

        $complaint['complainant'] = $this->customerLabel($complaint['customer_id'] ?? null);

        $complaint['disputed_transactions'] = collect($complaint['disputed_transactions'] ?? [])
            ->filter(fn ($row): bool => is_array($row))
            ->map(fn (array $row): array => [
                ...$row,
                'transaction' => str_replace('_', ' ', ucfirst((string) ($row['transaction_type'] ?? ''))).' #'.($row['transaction_id'] ?? '—'),
                'winner' => $this->customerLabel($row['customer_id'] ?? null),
                'source_wallet' => str_replace('_', ' ', ucfirst((string) ($row['source_wallet_type'] ?? 'wallet'))).' #'.($row['source_wallet_id'] ?? '—'),
            ])
            ->values()
            ->all();

        $complaint['refunds'] = collect($complaint['refunds'] ?? [])
            ->filter(fn ($row): bool => is_array($row))
            ->map(fn (array $row): array => [...$row, 'customer' => $this->customerLabel($row['customer_id'] ?? null)])
            ->values()
            ->all();

        return $complaint;
    }

    /**
     * "Name (#id)" from the wallet API, falling back to the bare id when the lookup fails.
     */
    protected function customerLabel(int|string|null $customerId): string
    {
        if (blank($customerId)) {
            return '—';
        }

        try {
            $customer = Cache::remember("api_customer_{$customerId}", 300, fn (): array => app(GameApiService::class)->getCustomer($customerId));
        } catch (\Throwable) {
            $customer = [];
        }

        $name = trim((string) ($customer['name'] ?? ''));

        return $name === '' ? '#'.$customerId : "{$name} (#{$customerId})";
    }
}
