<?php

namespace App\Http\Controllers\Api;

use App\Enums\CompanyWithdrawStatus;
use App\Enums\WithdrawStatus;
use App\Http\Controllers\Controller;
use App\Models\CompanyWithdraw;
use App\Models\Withdraw;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaB2CResultController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info('M-Pesa B2C Result received', $payload);

        $result = $payload['Result'] ?? [];
        $conversationId = $result['ConversationID'] ?? null;
        $resultCode = $result['ResultCode'] ?? -1;
        $resultDesc = $result['ResultDesc'] ?? '';

        if (! $conversationId) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $newStatus = $resultCode == 0
            ? WithdrawStatus::Completed->value
            : WithdrawStatus::Failed->value;

        $withdraw = Withdraw::where('receipt', $conversationId)
            ->orWhere('conversation_id', $conversationId)
            ->first();

        if ($withdraw) {
            $withdraw->update(['status' => $newStatus, 'response' => $resultDesc]);
        }

        $companyWithdrawStatus = $resultCode == 0
            ? CompanyWithdrawStatus::Completed->value
            : CompanyWithdrawStatus::Failed->value;

        $companyWithdraw = CompanyWithdraw::where('receipt', $conversationId)
            ->orWhere('conversation_id', $conversationId)
            ->first();

        if ($companyWithdraw) {
            $companyWithdraw->update(['status' => $companyWithdrawStatus, 'response' => $resultDesc]);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
