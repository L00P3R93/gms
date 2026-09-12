<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class C2BBalanceTimeoutController extends Controller
{
    /**
     * Handle the C2B account balance timeout callback from Safaricom.
     */
    public function __invoke(Request $request): JsonResponse
    {
        Log::channel('mpesa')->warning('C2B Balance Timeout: ', $request->all());

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'success']);
    }
}
