<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run against anything but the in-memory SQLite database.
     *
     * A cached config (php artisan optimize / config:cache) is loaded before phpunit's
     * environment overrides, which would point RefreshDatabase's migrate:fresh at the real
     * database. This guard fails before any test touches a connection.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = config('database.default');

        if ($connection !== 'sqlite' || config("database.connections.{$connection}.database") !== ':memory:') {
            throw new \RuntimeException(
                "Refusing to run tests against the [{$connection}] database. "
                .'The tests must use sqlite :memory:. Run `php artisan config:clear` and try again.'
            );
        }

        return $app;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Route::has('login')) {
            $this->markTestSkipped('Fortify routes are not registered (Fortify::ignoreRoutes() is active).');
        }

        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    protected function skipIfFortifyRoutesIgnored(): void
    {
        if (! Route::has('login')) {
            $this->markTestSkipped('Fortify routes are not registered (Fortify::ignoreRoutes() is active).');
        }
    }
}
