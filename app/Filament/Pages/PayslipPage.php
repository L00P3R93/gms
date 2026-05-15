<?php

namespace App\Filament\Pages;

use App\Models\Account;
use App\Models\PlayedGame;
use App\Services\GameApiService;
use App\Traits\SuperAdminAccess;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use UnitEnum;

class PayslipPage extends Page
{
    use SuperAdminAccess;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Payslip';

    protected static string|UnitEnum|null $navigationGroup = 'Players';

    protected static ?int $navigationSort = 4;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.payslip';

    public ?int $selectedAccountId = null;

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('selectedAccountId')
                ->label('Select Player')
                ->options(Account::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(fn () => null),
        ]);
    }

    public function getPlayerData(): ?array
    {
        if (! $this->selectedAccountId) {
            return null;
        }

        $account = Account::find($this->selectedAccountId);
        if (! $account) {
            return null;
        }

        $gameApi = app(GameApiService::class);

        try {
            $txResponse = Cache::remember("payslip_{$account->id}", 300, fn () => $gameApi->getCustomerTransactions($account->id, 'all'));
            $transactions = $txResponse['transactions'] ?? [];
            $purchases = Cache::remember("payslip_purchases_{$account->id}", 300, fn () => $gameApi->getCustomerPurchases($account->id));
        } catch (\Throwable) {
            $transactions = [];
            $purchases = [];
        }

        $totalDeposits = collect($transactions)->where('payment_type', 'deposit')->sum('amount');
        $totalWithdrawals = collect($transactions)->where('payment_type', 'withdrawal')->sum('amount');
        $totalPurchases = collect($purchases)->sum('amount');

        $singleTypes = [PlayedGame::TYPE_MULTI_2, PlayedGame::TYPE_MULTI_3, PlayedGame::TYPE_MULTI_4];
        $playerFilter = fn ($q) => $q->where('player_1', $account->id)
            ->orWhere('player_2', $account->id)
            ->orWhere('player_3', $account->id)
            ->orWhere('player_4', $account->id);

        $singleGamesWon = PlayedGame::whereIn('match_type', $singleTypes)->where('winner', (string) $account->id)->count();
        $singleGamesPlayed = PlayedGame::whereIn('match_type', $singleTypes)->where($playerFilter)->count();

        return [
            'account' => $account,
            'totalDeposits' => $totalDeposits,
            'totalWithdrawals' => $totalWithdrawals,
            'totalPurchases' => $totalPurchases,
            'gamesPlayed' => $singleGamesPlayed,
            'gamesWon' => $singleGamesWon,
            'winRate' => $singleGamesPlayed > 0
                ? round(($singleGamesWon / $singleGamesPlayed) * 100, 1)
                : 0,
        ];
    }

    public function downloadPdf(): Response
    {
        $data = $this->getPlayerData();

        if (! $data) {
            Notification::make()
                ->title('Please select a player first')
                ->warning()
                ->send();

            return response()->noContent();
        }

        $pdf = Pdf::loadView('filament.pages.payslip-pdf', $data)
            ->setPaper('a4', 'portrait');

        return $pdf->download("payslip_{$data['account']->id}_{$data['account']->name}.pdf");
    }
}
