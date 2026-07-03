<?php

use App\Jobs\ProcessIncomeDistributionJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

/*Schedule::command('balances:update')
    ->hourly()
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::error('balances:update scheduled run failed');
    });

Schedule::command('mpesa:balance')
    ->everyThirtyMinutes()
    ->withoutOverlapping();*/

Schedule::job(new ProcessIncomeDistributionJob)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::error('income_distribution scheduled run failed');
    });
