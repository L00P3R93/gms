<?php

use App\Enums\CompanyWithdrawStatus;
use App\Enums\HolderStatus;
use App\Enums\UserStatus;
use App\Enums\WithdrawStatus;
use App\Enums\WithdrawType;
use App\Filament\Widgets\ShareDistributionChartWidget;
use App\Filament\Widgets\ShareholdersTableWidget;
use App\Filament\Widgets\WithdrawalsThisMonthWidget;
use App\Models\CompanyWithdraw;
use App\Models\Holder;
use App\Models\User;
use App\Models\Withdraw;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->admin = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->admin->assignRole('super-admin');
});

it('the Users stats widget no longer exists', function (): void {
    expect(class_exists('App\Filament\Widgets\UserStatsWidget'))->toBeFalse();
});

it('sums completed company and shareholder withdrawals for the current month', function (): void {
    CompanyWithdraw::create([
        'phone' => '254700000001', 'amount' => 5000, 'user_id' => $this->admin->id,
        'reason' => 'Operations', 'status' => CompanyWithdrawStatus::Completed->value,
    ]);
    CompanyWithdraw::create([
        'phone' => '254700000002', 'amount' => 1000, 'user_id' => $this->admin->id,
        'reason' => 'Awaiting', 'status' => CompanyWithdrawStatus::Pending->value,
    ]);
    Withdraw::create([
        'receiver_id' => 1, 'type' => WithdrawType::Holder->value, 'phone' => '254700000003',
        'amount' => 2000, 'status' => WithdrawStatus::Completed->value,
    ]);

    $this->actingAs($this->admin);

    Livewire::test(WithdrawalsThisMonthWidget::class)
        ->assertSee('KES 5,000.00')
        ->assertSee('KES 2,000.00')
        ->assertSee('KES 7,000.00')
        ->assertSee('1 pending approval');
});

it('excludes withdrawals from previous months', function (): void {
    $old = CompanyWithdraw::create([
        'phone' => '254700000009', 'amount' => 9999, 'user_id' => $this->admin->id,
        'reason' => 'Old', 'status' => CompanyWithdrawStatus::Completed->value,
    ]);
    $old->update(['created_at' => now()->subMonthNoOverflow()->startOfMonth()]);

    $this->actingAs($this->admin);

    Livewire::test(WithdrawalsThisMonthWidget::class)
        ->assertSee('KES 0.00')
        ->assertDontSee('KES 9,999.00');
});

it('lists shareholders in the dashboard table widget', function (): void {
    Holder::create([
        'name' => 'Jane Shareholder', 'phone' => '254700000010', 'id_no' => '12345678',
        'share' => 0.25, 'status' => HolderStatus::Active->value, 'user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);

    Livewire::test(ShareholdersTableWidget::class)
        ->assertCanSeeTableRecords(Holder::all())
        ->assertSee('Jane Shareholder');
});

it('renders the share ownership distribution chart', function (): void {
    Holder::create([
        'name' => 'Major Holder', 'phone' => '254700000011', 'id_no' => '87654321',
        'share' => 0.6, 'status' => HolderStatus::Active->value, 'user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);

    Livewire::test(ShareDistributionChartWidget::class)->assertOk();
});

it('hides dashboard widgets from non super-admin users', function (): void {
    $agent = User::factory()->create(['status' => UserStatus::Active->value]);
    $agent->assignRole('agent');

    $this->actingAs($agent);

    expect(WithdrawalsThisMonthWidget::canView())->toBeFalse()
        ->and(ShareholdersTableWidget::canView())->toBeFalse()
        ->and(ShareDistributionChartWidget::canView())->toBeFalse();
});
