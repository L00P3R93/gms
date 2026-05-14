<?php

use App\Http\Controllers\Api\MpesaB2CResultController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/b2c/result', [MpesaB2CResultController::class, 'handle']);
Route::post('/v1/b2c/timeout', [MpesaB2CResultController::class, 'handle']);
