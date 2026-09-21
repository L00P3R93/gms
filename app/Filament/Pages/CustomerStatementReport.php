<?php

namespace App\Filament\Pages;

use App\Services\GameApiService;
use App\Support\Format;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Livewire\Attributes\Url;

/**
 * Wallet statement for one customer. Reached from a player's profile (or by
 * entering a customer ID), so it is not listed in the navigation.
 */
class CustomerStatementReport extends FinanceListReportPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Customer Statement';

    protected static bool $shouldRegisterNavigation = false;

    protected ?string $heading = 'Customer Wallet Statement';

    #[Url]
    public ?int $customerId = null;

    protected function reportKey(): string
    {
        return 'customers/'.app(GameApiService::class)->encryptId((string) $this->customerId).'/statement';
    }

    public function getReport(): array
    {
        if ($this->customerId === null) {
            return $this->cachedReport = [];
        }

        return parent::getReport();
    }

    protected function summaryStats(array $data): array
    {
        if ($data === []) {
            return [];
        }

        $statement = $data['statement'] ?? [];
        $reconciles = (bool) ($statement['reconciles'] ?? false);

        return [
            ['label' => ($data['customer']['name'] ?? 'Customer').' · Now', 'value' => Format::money($data['wallet']['balance_now'] ?? 0), 'description' => 'Account '.($data['customer']['account_no'] ?? '—'), 'icon' => 'heroicon-m-wallet', 'color' => 'primary'],
            ['label' => 'Opening → Closing', 'value' => Format::money($statement['opening_balance'] ?? 0).' → '.Format::money($statement['closing_balance'] ?? 0), 'description' => number_format((int) ($statement['entries'] ?? 0)).' entries', 'icon' => 'heroicon-m-arrows-right-left', 'color' => 'info'],
            ['label' => 'Credits / Debits', 'value' => Format::money($statement['credits'] ?? 0).' / '.Format::money($statement['debits'] ?? 0), 'icon' => 'heroicon-m-banknotes', 'color' => 'success'],
            ['label' => 'Statement Check', 'value' => $reconciles ? 'Reconciles' : 'Does not reconcile', 'description' => 'Opening + credits − debits = closing', 'icon' => $reconciles ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle', 'color' => $reconciles ? 'success' : 'danger'],
        ];
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('created_at')
                ->label('Time')
                ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
            TextColumn::make('entry_type')
                ->label('Entry')
                ->badge()
                ->color('info')
                ->formatStateUsing(fn (?string $state): string => str((string) $state)->replace('_', ' ')->title()->toString()),
            TextColumn::make('category')
                ->color('gray')
                ->formatStateUsing(fn (?string $state): string => str((string) $state)->replace('_', ' ')->title()->toString()),
            TextColumn::make('debit')
                ->alignEnd()
                ->formatStateUsing(fn ($state): string => (float) $state > 0 ? Format::money($state) : '—'),
            TextColumn::make('credit')
                ->alignEnd()
                ->formatStateUsing(fn ($state): string => (float) $state > 0 ? Format::money($state) : '—'),
            TextColumn::make('balance_after')
                ->label('Balance')
                ->alignEnd()
                ->weight('bold')
                ->formatStateUsing(fn ($state): string => Format::money($state)),
            TextColumn::make('status')
                ->badge()
                ->color(fn (?string $state): string => $state === 'reversed' ? 'danger' : 'success')
                ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state)),
            TextColumn::make('reason')
                ->color('gray')
                ->placeholder('—'),
        ];
    }

    protected function emptyHeading(): string
    {
        return $this->customerId === null ? 'Choose a customer to view a statement' : 'No entries in this period';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('chooseCustomer')
                ->label($this->customerId ? 'Customer #'.$this->customerId : 'Choose customer')
                ->icon('heroicon-o-user')
                ->color('gray')
                ->modalHeading('Customer statement')
                ->modalWidth('md')
                ->fillForm(fn (): array => ['customerId' => $this->customerId])
                ->schema([TextInput::make('customerId')->label('Customer ID')->numeric()->minValue(1)->required()])
                ->action(function (array $data): void {
                    $this->customerId = (int) $data['customerId'];
                    $this->onFilterApplied();
                }),
            ...parent::getHeaderActions(),
        ];
    }
}
