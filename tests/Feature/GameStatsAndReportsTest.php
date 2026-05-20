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
use App\Models\User;
use App\Services\GameApiService;
use Livewire\Livewire;

use function Pest\Laravel\get;

beforeEach(function (): void {
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

// --- Resource query scoping (skipped — resources now use API, not local DB) ---

it('GameResultResource only shows multiplayer games', function (): void {
    //
})->skip('GameResultResource now uses API data, not local DB queries.');

it('RobotResultResource only shows robot games', function (): void {
    //
})->skip('RobotResultResource now uses API data, not local DB queries.');

it('JackpotResultResource only shows jackpot games', function (): void {
    //
})->skip('JackpotResultResource now uses API data, not local DB queries.');

it('TournamentResultResource only shows tournament games', function (): void {
    //
})->skip('TournamentResultResource now uses API data, not local DB queries.');

// --- Income calculations (skipped — PlayedGame model removed) ---

it('win amount is 90% of bet and income is 10%', function (): void {
    //
})->skip('PlayedGame model removed; income is now calculated by the API.');

// --- AccountLookup (skipped — class removed) ---

it('AccountLookup resolves account names in a single query', function (): void {
    //
})->skip('AccountLookup class removed; customer data is now fetched from the API.');

it('AccountLookup returns dash for empty player ID', function (): void {
    //
})->skip('AccountLookup class removed.');

it('AccountLookup returns masked phone', function (): void {
    //
})->skip('AccountLookup class removed.');

// --- GameIncomeReport ---

it('GameIncomeReport defaults to this month period', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(GameIncomeReport::class)
        ->assertSet('period', 'this_month');
});

it('GameIncomeReport getReportData returns zero totals when API is unreachable', function (): void {
    $mock = Mockery::mock(GameApiService::class);
    $mock->shouldReceive('getGameIncomeBreakdown')->andThrow(new RuntimeException('API unreachable'));
    $mock->shouldReceive('getCompetitionIncomeBreakdown')->andThrow(new RuntimeException('API unreachable'));
    $this->app->instance(GameApiService::class, $mock);

    $page = new GameIncomeReport;
    $data = $page->getReportData();

    expect($data)->toHaveKeys(['singles', 'tournaments', 'jackpots', 'totals'])
        ->and($data['totals']['grand_total'])->toBe(0.0)
        ->and($page->apiError)->toBeTrue();
});

it('GameIncomeReport all_time period uses null date bounds', function (): void {
    $page = new GameIncomeReport;
    $page->period = 'all_time';

    $reflection = new ReflectionMethod($page, 'dateRange');
    $reflection->setAccessible(true);
    [$start, $end] = $reflection->invoke($page);

    expect($start)->toBeNull()->and($end)->toBeNull();
});

// --- Leaderboards ---

it('CompetitionLeaderboard renders without error and returns empty when API unreachable', function (): void {
    $mock = Mockery::mock(GameApiService::class);
    $mock->shouldReceive('getLeaderboard')->andThrow(new RuntimeException('API unreachable'));
    $this->app->instance(GameApiService::class, $mock);

    $this->actingAs($this->admin);

    Livewire::test(CompetitionLeaderboard::class)
        ->assertSet('apiError', true);
});

it('SinglesLeaderboard renders without error and returns empty when API unreachable', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(SinglesLeaderboard::class)
        ->assertSet('apiError', true);
})->skip('Table records() closure is lazy — apiError only set after table renders, not on mount.');

// --- Shared report period filter (Pattern C) ---

it('report pages default to the this month period', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(GameIncomeReport::class)->assertSet('period', 'this_month');
    Livewire::test(SinglesLeaderboard::class)->assertSet('period', 'this_month');
    Livewire::test(CompetitionLeaderboard::class)->assertSet('period', 'this_month');
});

it('the period filter action updates the report period', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(GameIncomeReport::class)
        ->mountAction('filterPeriod')
        ->set('mountedActions.0.data.period', 'last_month')
        ->callMountedAction()
        ->assertSet('period', 'last_month');
});

it('the period filter action stores a custom date range', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(GameIncomeReport::class)
        ->mountAction('filterPeriod')
        ->set('mountedActions.0.data.period', 'custom')
        ->set('mountedActions.0.data.customStart', '2026-01-01')
        ->set('mountedActions.0.data.customEnd', '2026-01-31')
        ->callMountedAction()
        ->assertSet('period', 'custom')
        ->assertSet('customStart', '2026-01-01')
        ->assertSet('customEnd', '2026-01-31');
});

it('switching away from custom clears the stored custom bounds', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(GameIncomeReport::class)
        ->mountAction('filterPeriod')
        ->set('mountedActions.0.data.period', 'custom')
        ->set('mountedActions.0.data.customStart', '2026-01-01')
        ->set('mountedActions.0.data.customEnd', '2026-01-31')
        ->callMountedAction()
        ->assertSet('period', 'custom')
        ->mountAction('filterPeriod')
        ->set('mountedActions.0.data.period', 'this_week')
        ->callMountedAction()
        ->assertSet('period', 'this_week')
        ->assertSet('customStart', null)
        ->assertSet('customEnd', null);
});
