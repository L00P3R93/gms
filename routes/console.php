<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Schedule::command('balances:update')
    ->hourly()
    ->runInBackground()
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::error('balances:update scheduled run failed');
    });

Schedule::command('mpesa:balance')
    ->everyThirtyMinutes()
    ->runInBackground()
    ->withoutOverlapping();
