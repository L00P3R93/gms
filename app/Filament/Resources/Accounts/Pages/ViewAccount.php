<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Resources\Accounts\AccountResource;
use App\Models\Account;
use App\Models\PlayedGame;
use App\Services\GameApiService;
use Filament\Resources\Pages\Page;

class ViewAccount extends Page
{
    protected static string $resource = AccountResource::class;

    protected string $view = 'filament.resources.accounts.pages.view-account';

    public Account $record;

    public string $activeTab = 'single_games';

    public array $singleGames = [];

    public array $tournamentGames = [];

    public array $jackpotGames = [];

    public array $deposits = [];

    public array $withdrawals = [];

    public array $purchases = [];

    /** @var array<int|string, string> */
    public array $winnerNames = [];

    public function mount(int|string $record): void
    {
        $this->record = Account::findOrFail($record);
        $this->loadData();
    }

    private function loadData(): void
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

        try {
            $transactions = app(GameApiService::class)->getAccountTransactions($this->record->encrypted_id);
            $this->deposits = $transactions['deposits'] ?? [];
            $this->withdrawals = $transactions['withdrawals'] ?? [];
            $this->purchases = $transactions['purchases'] ?? [];
        } catch (\Throwable) {
            $this->deposits = [];
            $this->withdrawals = [];
            $this->purchases = [];
        }
    }
}
