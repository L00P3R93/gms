<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
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
