<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\CompanyWithdraw;
use App\Models\Dependant;
use App\Models\Expense;
use App\Models\Holder;
use App\Models\Payee;
use App\Models\Payout;
use App\Models\User;
use App\Models\Withdraw;
use App\Policies\AuditLogPolicy;
use App\Policies\CompanyWithdrawPolicy;
use App\Policies\DependantPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\HolderPolicy;
use App\Policies\PayeePolicy;
use App\Policies\PayoutPolicy;
use App\Policies\UserPolicy;
use App\Policies\WithdrawPolicy;
use App\Services\EncryptionService;
use App\Services\GameApiService;
use App\Services\MpesaService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MpesaService::class);
        $this->app->singleton(GameApiService::class);
        $this->app->singleton(EncryptionService::class);
    }

    public function boot(): void
    {
        $this->configurePolicies();
        $this->configureGates();
        $this->configureDefaults();
    }

    protected function configurePolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Holder::class, HolderPolicy::class);
        Gate::policy(Dependant::class, DependantPolicy::class);
        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(Withdraw::class, WithdrawPolicy::class);
        Gate::policy(CompanyWithdraw::class, CompanyWithdrawPolicy::class);
        Gate::policy(Payee::class, PayeePolicy::class);
        Gate::policy(Payout::class, PayoutPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
    }

    protected function configureGates(): void
    {
        Gate::define('viewLogViewer', fn (?User $user): bool => $user?->isAdmin() ?? false);
    }

    protected function configureDefaults(): void
    {
        Model::automaticallyEagerLoadRelationships();
        Model::unguard();

        if (app()->environment('production')) {
            URL::forceHttps();
        }

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

        // Compatibility fix for Filament v5 with Laravel 13
        Collection::macro('clone', function (): Collection {
            return clone $this;
        });
    }
}
