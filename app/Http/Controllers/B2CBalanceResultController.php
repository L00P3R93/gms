<?php

namespace App\Http\Controllers;

use App\Models\MpesaAccountBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class B2CBalanceResultController extends Controller
{
    /**
     * Handle the B2C account balance result callback from Safaricom.
     */
    public function __invoke(Request $request): JsonResponse
    {
        Log::channel('mpesa')->info('B2C Balance Result: ', $request->all());

        try {
            $result = $request->input('Result');

            if (is_array($result)) {
                MpesaAccountBalance::storeFromCallback('b2c', $result);
            }
        } catch (\Throwable $e) {
            Log::channel('mpesa')->error('B2C Balance Result handling error: '.$e->getMessage());
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'success']);
    }
}
