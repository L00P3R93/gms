<?php

namespace App\Services;

use App\Exceptions\GameApiException;
use App\Models\ApiIncomeLog;
use App\Models\Holder;
use App\Models\HolderWallet;
use App\Models\IncomeDistribution;
use App\Models\IncomeProcessingState;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IncomeDistributionService
{
    public function __construct(
        protected GameApiService $gameApiService
    ) {}

    /**
     * Process income distribution for the current business day.
     *
     * @return array{success: bool, message: string, distribution_id?: int, delta?: float}
     */
    public function processDistribution(): array
    {
        try {
            // Step 1: Call the API and store the raw response
            $apiResponse = $this->gameApiService->getCurrentDayIncome();
            $currentApiTotal = (float) ($apiResponse['total_income'] ?? 0);

            // Step 2: Store the raw API response
            $this->logApiCall($currentApiTotal, $apiResponse);

            // Step 3: Begin database transaction and load processing state with row locking
            return DB::transaction(function () use ($currentApiTotal) {
                $currentBusinessDate = now()->toDateString();

                $processingState = IncomeProcessingState::where('business_date', $currentBusinessDate)
                    ->lockForUpdate()
                    ->first();

                if (! $processingState) {
                    $processingState = IncomeProcessingState::create([
                        'business_date' => $currentBusinessDate,
                        'last_processed_total' => 0,
                        'last_api_total' => 0,
                        'last_checked_at' => now(),
                    ]);
                }

                // Step 5: Compute delta
                $lastProcessedTotal = (float) $processingState->last_processed_total;
                $delta = $currentApiTotal - $lastProcessedTotal;

                // Step 6: Exit if delta <= 0 (no new income to distribute)
                if ($delta <= 0) {
                    $processingState->update([
                        'last_api_total' => $currentApiTotal,
                        'last_checked_at' => now(),
                    ]);

                    return [
                        'success' => true,
                        'message' => 'No new income to distribute',
                        'delta' => 0,
                    ];
                }

                // Step 7: Create income distribution record
                $distribution = IncomeDistribution::create([
                    'previous_total' => $lastProcessedTotal,
                    'current_total' => $currentApiTotal,
                    'delta' => $delta,
                    'processed_at' => now(),
                    'status' => 'completed',
                    'notes' => null,
                ]);

                // Step 8: Get active shareholders ordered by sort_order
                $shareholders = Holder::where('status', 'active')
                    ->orderBy('sort_order')
                    ->get();

                // Step 9: Calculate and distribute allocations
                $this->distributeToShareholders($shareholders, $delta, $distribution->id);

                // Step 10: Update processing state
                $processingState->update([
                    'last_processed_total' => $currentApiTotal,
                    'last_api_total' => $currentApiTotal,
                    'last_checked_at' => now(),
                ]);

                Log::info('Income distribution completed successfully', [
                    'distribution_id' => $distribution->id,
                    'delta' => $delta,
                    'shareholders_count' => $shareholders->count(),
                ]);

                return [
                    'success' => true,
                    'message' => 'Income distributed successfully',
                    'distribution_id' => $distribution->id,
                    'delta' => $delta,
                ];
            });

        } catch (GameApiException $e) {
            Log::error('Game API error during income distribution', [
                'error' => $e->getMessage(),
                'code' => $e->statusCode,
            ]);

            return [
                'success' => false,
                'message' => 'Game API error: '.$e->getMessage(),
            ];
        } catch (\Throwable $e) {
            Log::error('Unexpected error during income distribution', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Unexpected error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Log the API call response.
     */
    protected function logApiCall(float $apiTotal, array $rawResponse): void
    {
        ApiIncomeLog::create([
            'api_total' => $apiTotal,
            'raw_response' => $rawResponse,
            'business_date' => now()->toDateString(),
        ]);
    }

    /**
     * Distribute income to shareholders with deterministic rounding.
     */
    protected function distributeToShareholders($shareholders, float $delta, int $distributionId): void
    {
        $totalDistributed = 0;
        $remaining = $delta;

        foreach ($shareholders as $index => $shareholder) {
            // Calculate the allocation for this shareholder
            $allocation = $this->calculateAllocation($shareholder->share, $delta, $remaining, $index, $shareholders->count());

            // Get or create wallet
            $wallet = $shareholder->wallet ?? HolderWallet::create([
                'holder_id' => $shareholder->id,
                'balance' => 0,
            ]);

            $balanceBefore = $wallet->balance;
            $balanceAfter = $balanceBefore + $allocation;

            // Update wallet balance
            $wallet->update(['balance' => $balanceAfter]);

            // Create wallet transaction record
            WalletTransaction::create([
                'holder_id' => $shareholder->id,
                'distribution_id' => $distributionId,
                'amount' => $allocation,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => "Income distribution - {$shareholder->name}",
                'transaction_type' => 'credit',
            ]);

            $totalDistributed += $allocation;
            $remaining -= $allocation;
        }

        // Verify that the total distributed equals the delta
        if (abs($totalDistributed - $delta) > 0.0001) {
            throw new \Exception("Distribution mismatch: expected {$delta}, distributed {$totalDistributed}");
        }
    }

    /**
     * Calculate allocation with deterministic rounding.
     * The last shareholder gets any remainder to ensure exact totals.
     */
    protected function calculateAllocation(float $sharePercentage, float $delta, float $remaining, int $index, int $totalCount): float
    {
        if ($index === $totalCount - 1) {
            // Last shareholder gets the remaining amount
            return $remaining;
        }

        // Calculate allocation using percentage
        $allocation = $delta * $sharePercentage;

        // Round to 4 decimal places
        return round($allocation, 4);
    }
}
