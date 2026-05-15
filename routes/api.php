<?php

use App\Http\Controllers\Api\MpesaB2CResultController;
use App\Http\Middleware\SafaricomIpWhitelist;
use Illuminate\Support\Facades\Route;

Route::middleware(SafaricomIpWhitelist::class)->group(function () {
    Route::post('/v1/b2c/result', [MpesaB2CResultController::class, 'handle']);
    Route::post('/v1/b2c/timeout', [MpesaB2CResultController::class, 'handle']);
});
