<?php

namespace App\Http\Controllers;

use App\Models\MpesaAccountBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class C2BBalanceResultController extends Controller
{
    /**
     * Handle the C2B account balance result callback from Safaricom.
     */
    public function __invoke(Request $request): JsonResponse
    {
        Log::channel('mpesa')->info('C2B Balance Result: ', $request->all());

        try {
            $result = $request->input('Result');

            if (is_array($result)) {
                MpesaAccountBalance::storeFromCallback('c2b', $result);
            }
        } catch (\Throwable $e) {
            Log::channel('mpesa')->error('C2B Balance Result handling error: '.$e->getMessage());
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'success']);
    }
}
