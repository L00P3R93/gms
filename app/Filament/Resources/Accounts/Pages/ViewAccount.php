<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Resources\Accounts\AccountResource;
use App\Models\Account;
use App\Models\PlayedGame;
use App\Services\GameApiService;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Cache;

class ViewAccount extends Page
{
    protected static string $resource = AccountResource::class;

    protected string $view = 'filament.resources.accounts.pages.view-account';

    public string $activeTab = 'single_games';

    public array $singleGames = [];

    public array $tournamentGames = [];

    public array $jackpotGames = [];

    public array $deposits = [];

    public array $withdrawals = [];

    public array $purchases = [];

    /** @var array<int|string, string> */
    public array $winnerNames = [];

    public ?array $walletInfo = null;

    public ?array $apiCustomer = null;

    public bool $apiUnavailable = false;

    public function mount(int|string $record): void
    {
        $this->record = Account::findOrFail($record);
        $this->loadLocalGames();
        $this->loadApiData();
    }

    private function loadLocalGames(): void
    {
        $id = $this->record->id;

        $playerFilter = fn ($q) => $q->where('player_1', $id)
            ->orWhere('player_2', $id)
            ->orWhere('player_3', $id)
            ->orWhere('player_4', $id)
            ->orWhere('player_5', $id)
            ->orWhere('player_6', $id);

        $this->singleGames = PlayedGame::whereIn('match_type', [
            PlayedGame::TYPE_MULTI_2,
            PlayedGame::TYPE_MULTI_3,
            PlayedGame::TYPE_MULTI_4,
        ])->where($playerFilter)->orderByDesc('time')->get()->toArray();

        $this->tournamentGames = PlayedGame::where('match_type', PlayedGame::TYPE_TOURNAMENT)
            ->where($playerFilter)->orderByDesc('time')->get()->toArray();

        $this->jackpotGames = PlayedGame::where('match_type', PlayedGame::TYPE_JACKPOT)
            ->where($playerFilter)->orderByDesc('time')->get()->toArray();

        $allWinnerIds = collect($this->singleGames)
            ->merge($this->tournamentGames)
            ->merge($this->jackpotGames)
            ->pluck('winner')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if ($allWinnerIds) {
            $this->winnerNames = Account::whereIn('id', $allWinnerIds)
                ->pluck('name', 'id')
                ->toArray();
        }
    }

    private function loadApiData(): void
    {
        $customerId = $this->record->id;
        $gameApi = app(GameApiService::class);

        $anyFailed = false;

        try {
            $this->apiCustomer = Cache::remember(
                "api_customer_{$customerId}",
                300,
                fn () => $gameApi->getCustomer($customerId)
            );

            if (! empty($this->apiCustomer['wallet_id'])) {
                $this->walletInfo = [
                    'balance' => $this->apiCustomer['balance'] ?? 0,
                    'deposits' => $this->apiCustomer['deposits'] ?? 0,
                    'withdraws' => $this->apiCustomer['withdraws'] ?? 0,
                    'purchases_load' => $this->apiCustomer['purchases_load'] ?? 0,
                    'purchases_gift' => $this->apiCustomer['purchases_gift'] ?? 0,
                    'purchases_emojis' => $this->apiCustomer['purchases_emojis'] ?? 0,
                    'coins' => $this->apiCustomer['coins'] ?? 0,
                ];
            }
        } catch (\Throwable) {
            $anyFailed = true;
            $this->apiCustomer = null;
            $this->walletInfo = null;
        }

        try {
            $txResponse = Cache::remember(
                "api_transactions_{$customerId}",
                300,
                fn () => $gameApi->getCustomerTransactions($customerId, 'all')
            );
            $transactions = $txResponse['transactions'] ?? [];
            $this->deposits = array_values(array_filter($transactions, fn ($tx) => ($tx['payment_type'] ?? '') === 'deposit'));
            $this->withdrawals = array_values(array_filter($transactions, fn ($tx) => ($tx['payment_type'] ?? '') === 'withdrawal'));
        } catch (\Throwable) {
            $anyFailed = true;
            $this->deposits = [];
            $this->withdrawals = [];
        }

        try {
            $this->purchases = Cache::remember(
                "api_purchases_{$customerId}",
                300,
                fn () => $gameApi->getCustomerPurchases($customerId)
            );
        } catch (\Throwable) {
            $anyFailed = true;
            $this->purchases = [];
        }

        $this->apiUnavailable = $anyFailed;
    }
}
