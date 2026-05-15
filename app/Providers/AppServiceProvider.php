<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Bug;
use App\Models\CompanyWithdraw;
use App\Models\Dependant;
use App\Models\Expense;
use App\Models\Holder;
use App\Models\PlayedGame;
use App\Models\User;
use App\Models\Withdraw;
use App\Policies\AccountPolicy;
use App\Policies\BugPolicy;
use App\Policies\CompanyWithdrawPolicy;
use App\Policies\DependantPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\HolderPolicy;
use App\Policies\PlayedGamePolicy;
use App\Policies\UserPolicy;
use App\Policies\WithdrawPolicy;
use App\Services\EncryptionService;
use App\Services\GameApiService;
use App\Services\MpesaService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MpesaService::class);
        $this->app->singleton(GameApiService::class);
        $this->app->singleton(EncryptionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePolicies();
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configurePolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Holder::class, HolderPolicy::class);
        Gate::policy(Dependant::class, DependantPolicy::class);
        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(Bug::class, BugPolicy::class);
        Gate::policy(Withdraw::class, WithdrawPolicy::class);
        Gate::policy(CompanyWithdraw::class, CompanyWithdrawPolicy::class);
        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(PlayedGame::class, PlayedGamePolicy::class);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
