<?php

use App\Jobs\ProcessIncomeDistributionJob;
use App\Jobs\ProcessPayoutsJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

//Schedule::job(new ProcessIncomeDistributionJob)
//    ->everyFiveMinutes()
//    ->withoutOverlapping()
//    ->onFailure(function () {
//        Log::error('income_distribution scheduled run failed');
//    });

//Schedule::job(new ProcessPayoutsJob)
//    ->everyMinute()
//    ->withoutOverlapping()
//    ->onFailure(function () {
//        Log::error('process_payouts scheduled run failed');
//    });

Schedule::command('payouts:process-pending')
    ->everyMinute()
    ->withoutOverlapping()
    ->onFailure(fn () => Log::channel('mpesa')->error('B2C processing pending payouts failed'));

Schedule::command('mpesa:fetch-balances')
    ->hourly()
    ->timezone('Africa/Nairobi')
    ->withoutOverlapping()
    ->onFailure(fn () => Log::channel('mpesa')->error('M-Pesa balance fetch job failed'));
