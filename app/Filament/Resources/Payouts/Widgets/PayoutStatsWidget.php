<?php

namespace App\Filament\Resources\Payouts\Widgets;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Services\MpesaService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class PayoutStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $pendingPayouts = Payout::where('status', PayoutStatus::Pending)->get();
        $pendingCount = $pendingPayouts->count();
        $pendingAmount = $pendingPayouts->sum('amount');

        $approvedCount = Payout::whereIn('status', [PayoutStatus::Approved, PayoutStatus::Processing])->count();

        $thisMonthCompleted = Payout::where('status', PayoutStatus::Completed)
            ->whereMonth('processed_at', now()->month)
            ->whereYear('processed_at', now()->year)
            ->sum('amount');

        $failedCount = Payout::where('status', PayoutStatus::Failed)->count();

        $b2cBalance = Cache::remember('mpesa_b2c_balance', 300, function () {
            try {
                return app(MpesaService::class)->getB2CBalance();
            } catch (\Throwable) {
                return 0;
            }
        });

        return [
            Stat::make('Pending Payouts', $pendingCount)
                ->description('KES '.number_format($pendingAmount, 2).' pending')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            Stat::make('Approved / Queued', $approvedCount)
                ->description('Waiting for M-Pesa processing')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('info'),
            Stat::make('Paid This Month', 'KES '.number_format($thisMonthCompleted, 2))
                ->description('Completed payouts')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('Failed', $failedCount)
                ->description('Requires attention')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('danger'),
            Stat::make('M-Pesa B2C Balance', 'KES '.number_format($b2cBalance, 2))
                ->description('Available for payouts')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),
        ];
    }
}
