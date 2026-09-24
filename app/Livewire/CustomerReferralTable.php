<?php

namespace App\Livewire;

use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;

/**
 * Shared base for the paged KadiApi tables on a customer's Referrals tab. Each
 * one is mounted lazily and pages through the API, so nothing is fetched until
 * the tab is opened and only one page at a time.
 */
abstract class CustomerReferralTable extends TableWidget
{
    public int $customerId;

    public bool $apiError = false;

    protected int|string|array $columnSpan = 'full';

    public function mount(int $customerId): void
    {
        $this->customerId = $customerId;
    }

    /**
     * Run a KadiApi fetch, returning an empty payload and flagging the table when it fails.
     *
     * @param  callable(): array<string, mixed>  $fetch
     * @return array<string, mixed>
     */
    protected function fetchOrEmpty(callable $fetch): array
    {
        try {
            $response = $fetch();
            $this->apiError = false;

            return $response;
        } catch (\Throwable $e) {
            Log::warning('Customer referral table failed', ['table' => static::class, 'customer' => $this->customerId, 'error' => $e->getMessage()]);
            $this->apiError = true;

            return [];
        }
    }

    protected function configureReferralTable(Table $table, string $heading, string $emptyHeading): Table
    {
        return $table
            ->heading($heading)
            ->paginationMode(PaginationMode::Default)
            ->paginated([10, 20, 50])
            ->defaultPaginationPageOption(20)
            ->emptyStateHeading(fn (): string => $this->apiError ? 'Could not be loaded from KadiApi' : $emptyHeading)
            ->emptyStateDescription(fn (): ?string => $this->apiError ? 'Refresh the page to try again.' : null);
    }

    public function placeholder(): View
    {
        return view('livewire.customer-referral-placeholder');
    }
}
