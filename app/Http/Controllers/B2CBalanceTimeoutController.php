<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class B2CBalanceTimeoutController extends Controller
{
    /**
     * Handle the B2C account balance timeout callback from Safaricom.
     */
    public function __invoke(Request $request): JsonResponse
    {
        Log::channel('mpesa')->warning('B2C Balance Timeout: ', $request->all());

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'success']);
    }
}
