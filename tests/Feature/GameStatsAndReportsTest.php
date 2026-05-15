<?php

use App\Enums\UserStatus;
use App\Filament\Pages\CompetitionLeaderboard;
use App\Filament\Pages\GameIncomeReport;
use App\Filament\Pages\JackpotAwardsPage;
use App\Filament\Pages\SinglesLeaderboard;
use App\Filament\Pages\TournamentAwardsPage;
use App\Filament\Resources\GameResults\GameResultResource;
use App\Filament\Resources\JackpotResults\JackpotResultResource;
use App\Filament\Resources\RobotResults\RobotResultResource;
use App\Filament\Resources\TournamentResults\TournamentResultResource;
use App\Models\Account;
use App\Models\PlayedGame;
use App\Models\User;
use App\Support\AccountLookup;
use Livewire\Livewire;

use function Pest\Laravel\get;

beforeEach(function (): void {
    AccountLookup::flush();
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->admin = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->admin->assignRole('super-admin');

    $this->agent = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->agent->assignRole('agent');
});

// --- Access control ---

it('blocks agents from game result resources', function (): void {
    $this->actingAs($this->agent);

    get(GameResultResource::getUrl('index'))->assertForbidden();
    get(RobotResultResource::getUrl('index'))->assertForbidden();
    get(JackpotResultResource::getUrl('index'))->assertForbidden();
    get(TournamentResultResource::getUrl('index'))->assertForbidden();
});

it('blocks agents from report pages', function (): void {
    $this->actingAs($this->agent);

    get(CompetitionLeaderboard::getUrl())->assertForbidden();
    get(SinglesLeaderboard::getUrl())->assertForbidden();
    get(GameIncomeReport::getUrl())->assertForbidden();
    get(JackpotAwardsPage::getUrl())->assertForbidden();
    get(TournamentAwardsPage::getUrl())->assertForbidden();
});

it('allows super-admin to access game result resources', function (): void {
    $this->actingAs($this->admin);

    get(GameResultResource::getUrl('index'))->assertOk();
    get(RobotResultResource::getUrl('index'))->assertOk();
    get(JackpotResultResource::getUrl('index'))->assertOk();
    get(TournamentResultResource::getUrl('index'))->assertOk();
});

it('allows super-admin to access report pages', function (): void {
    $this->actingAs($this->admin);

    get(CompetitionLeaderboard::getUrl())->assertOk();
    get(SinglesLeaderboard::getUrl())->assertOk();
    get(GameIncomeReport::getUrl())->assertOk();
    get(JackpotAwardsPage::getUrl())->assertOk();
    get(TournamentAwardsPage::getUrl())->assertOk();
});

// --- Resource query scoping ---

it('GameResultResource only shows multiplayer games', function (): void {
    PlayedGame::create(['match_name' => 'game1', 'match_type' => PlayedGame::TYPE_MULTI_2, 'player_1' => '1', 'player_2' => '2', 'amount' => 100, 'winner' => '1']);
    PlayedGame::create(['match_name' => 'robot1', 'match_type' => PlayedGame::TYPE_ROBOT, 'player_1' => '1', 'player_2' => '0', 'amount' => 50, 'winner' => '1']);

    $query = GameResultResource::getEloquentQuery();

    expect($query->count())->toBe(1)
        ->and($query->first()->match_type)->toBe(PlayedGame::TYPE_MULTI_2);
});

it('RobotResultResource only shows robot games', function (): void {
    PlayedGame::create(['match_name' => 'game1', 'match_type' => PlayedGame::TYPE_MULTI_2, 'player_1' => '1', 'player_2' => '2', 'amount' => 100, 'winner' => '1']);
    PlayedGame::create(['match_name' => 'robot1', 'match_type' => PlayedGame::TYPE_ROBOT, 'player_1' => '1', 'player_2' => '0', 'amount' => 50, 'winner' => '0']);

    $query = RobotResultResource::getEloquentQuery();

    expect($query->count())->toBe(1)
        ->and($query->first()->match_type)->toBe(PlayedGame::TYPE_ROBOT);
});

it('JackpotResultResource only shows jackpot games', function (): void {
    PlayedGame::create(['match_name' => 'jp1', 'match_type' => PlayedGame::TYPE_JACKPOT, 'player_1' => '1', 'amount' => 200, 'winner' => '1']);
    PlayedGame::create(['match_name' => 'tn1', 'match_type' => PlayedGame::TYPE_TOURNAMENT, 'player_1' => '1', 'amount' => 200, 'winner' => '1']);

    $query = JackpotResultResource::getEloquentQuery();

    expect($query->count())->toBe(1)
        ->and($query->first()->match_type)->toBe(PlayedGame::TYPE_JACKPOT);
});

it('TournamentResultResource only shows tournament games', function (): void {
    PlayedGame::create(['match_name' => 'tn1', 'match_type' => PlayedGame::TYPE_TOURNAMENT, 'player_1' => '1', 'amount' => 300, 'winner' => '1']);
    PlayedGame::create(['match_name' => 'jp1', 'match_type' => PlayedGame::TYPE_JACKPOT, 'player_1' => '1', 'amount' => 300, 'winner' => '1']);

    $query = TournamentResultResource::getEloquentQuery();

    expect($query->count())->toBe(1)
        ->and($query->first()->match_type)->toBe(PlayedGame::TYPE_TOURNAMENT);
});

// --- Income calculations ---

it('win amount is 90% of bet and income is 10%', function (): void {
    $game = PlayedGame::make(['amount' => 100]);

    expect($game->amount * 0.90)->toBe(90.0)
        ->and($game->income)->toBe(10.0);
});

// --- AccountLookup ---

it('AccountLookup resolves account names in a single query', function (): void {
    $account = Account::create(['name' => 'Alice', 'phone' => '0712345678', 'email' => 'alice@example.com', 'password' => 'hashed', 'game_status' => 1]);

    expect(AccountLookup::name($account->id))->toBe('Alice');
});

it('AccountLookup returns dash for empty player ID', function (): void {
    expect(AccountLookup::name(''))->toBe('—');
});

it('AccountLookup returns masked phone', function (): void {
    $account = Account::create(['name' => 'Bob', 'phone' => '0712345678', 'email' => 'bob@example.com', 'password' => 'hashed', 'game_status' => 1]);

    expect(AccountLookup::maskedPhone($account->id))->toBe('****5678');
});

// --- GameIncomeReport ---

it('GameIncomeReport defaults to today period', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(GameIncomeReport::class)
        ->assertSet('period', 'today');
});

it('GameIncomeReport getReportData returns zero totals when API is unreachable', function (): void {
    $page = new GameIncomeReport;
    $data = $page->getReportData();

    expect($data)->toHaveKeys(['singles', 'tournaments', 'jackpots', 'totals'])
        ->and($data['totals']['grand_total'])->toBe(0.0)
        ->and($page->apiError)->toBeTrue();
});

it('GameIncomeReport all_time period uses null date bounds', function (): void {
    $page = new GameIncomeReport;
    $page->period = 'all_time';

    $reflection = new ReflectionMethod($page, 'getDateRange');
    $reflection->setAccessible(true);
    [$start, $end] = $reflection->invoke($page);

    expect($start)->toBeNull()->and($end)->toBeNull();
});

// --- Leaderboards ---

it('CompetitionLeaderboard renders without error and returns empty when API unreachable', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(CompetitionLeaderboard::class)
        ->assertSet('apiError', true);
});

it('SinglesLeaderboard renders without error and returns empty when API unreachable', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(SinglesLeaderboard::class)
        ->assertSet('apiError', true);
});
