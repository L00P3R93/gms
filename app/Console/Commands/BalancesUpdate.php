<?php

namespace App\Console\Commands;

use App\Enums\HolderStatus;
use App\Models\AccountSnapshot;
use App\Models\CompanyWallet;
use App\Models\Holder;
use App\Models\HolderWallet;
use App\Models\User;
use App\Services\GameApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BalancesUpdate extends Command
{
    protected $signature = 'balances:update {--dry-run : Simulate without committing}';

    protected $description = 'Distribute new M-Pesa revenue to company and shareholder wallets (40/60 split)';

    public function handle(GameApiService $gameApi): int
    {
        $isDryRun = $this->option('dry-run');
        $this->info('Starting revenue distribution'.($isDryRun ? ' [DRY RUN]' : '').'...');

        DB::beginTransaction();

        try {
            // Step 1: Fetch current M-Pesa B2C balance
            $currentMpesaBalance = $gameApi->getB2CBalanceAmount();
            $this->info("Current M-Pesa balance: KES {$currentMpesaBalance}");

            // Step 2: Fetch latest M-Pesa balance snapshot
            $latestMpesaSnapshot = AccountSnapshot::where('type', AccountSnapshot::TYPE_MPESA_BALANCE)
                ->latest('created_at')
                ->first();
            $lastMpesaBalance = $latestMpesaSnapshot ? $latestMpesaSnapshot->balance : 0;
            $this->info("Last snapshot M-Pesa balance: KES {$lastMpesaBalance}");

            // Step 3: Fetch current referral purchases total
            $agentReferralCodes = User::whereNotNull('referral_codes')->get()
                ->flatMap(fn ($u) => $u->referral_codes_array)
                ->filter()
                ->toArray();

            $currentReferralTotal = 0;
            if (! empty($agentReferralCodes)) {
                $referralData = $gameApi->getPurchasesByReferral($agentReferralCodes);
                $currentReferralTotal = is_array($referralData) ? collect($referralData)->sum('amount') : 0;
            }

            // Step 4: Fetch latest referral snapshot
            $latestReferralSnapshot = AccountSnapshot::where('type', AccountSnapshot::TYPE_REFERRAL_TOTAL)
                ->latest('created_at')
                ->first();
            $lastReferralTotal = $latestReferralSnapshot ? $latestReferralSnapshot->balance : 0;

            // Step 5: Calculate deltas and distribute
            $mpesaDiff = $currentMpesaBalance - $lastMpesaBalance;
            $this->info("M-Pesa diff (new income): KES {$mpesaDiff}");

            $companyShare = 0;
            $shareholderPool = 0;

            if ($mpesaDiff > 0) {
                $companyShare = $mpesaDiff * 0.40;
                $shareholderPool = $mpesaDiff * 0.60;

                $referralDiff = $currentReferralTotal - $lastReferralTotal;
                if ($referralDiff > 0) {
                    $referralShare = $referralDiff * 0.10;
                    $companyShare = $companyShare - $referralShare;

                    $referralWallet = CompanyWallet::find(CompanyWallet::REFERRAL_WALLET);
                    $referralWallet->increment('balance', $referralShare);
                    $referralWallet->update(['updated_at' => now()]);
                    $this->info("Referral commission: KES {$referralShare}");
                }

                $mainWallet = CompanyWallet::find(CompanyWallet::MAIN_WALLET);
                $mainWallet->increment('balance', $companyShare);
                $mainWallet->update(['updated_at' => now()]);
                $this->info("Company share: KES {$companyShare}");

                $holders = Holder::where('status', HolderStatus::Active->value)->get();
                foreach ($holders as $holder) {
                    $holderAmount = $shareholderPool * $holder->share;
                    if ($holderAmount > 0) {
                        $wallet = HolderWallet::firstOrCreate(
                            ['holder_id' => $holder->id],
                            ['balance' => 0.00]
                        );
                        $wallet->increment('balance', $holderAmount);
                        $wallet->update(['updated_at' => now()]);
                        $this->info("Holder {$holder->name}: +KES {$holderAmount}");
                    }
                }
            } else {
                $this->info('No new M-Pesa income detected. Skipping distribution.');
            }

            // Step 6: Always record new snapshots
            AccountSnapshot::create([
                'balance' => $currentMpesaBalance,
                'type' => AccountSnapshot::TYPE_MPESA_BALANCE,
                'created_at' => now(),
            ]);
            AccountSnapshot::create([
                'balance' => $currentReferralTotal,
                'type' => AccountSnapshot::TYPE_REFERRAL_TOTAL,
                'created_at' => now(),
            ]);

            if ($isDryRun) {
                DB::rollBack();
                $this->warn('DRY RUN — no changes committed');

                return self::SUCCESS;
            }

            DB::commit();
            $this->info('Revenue distribution complete.');
            Log::info('balances:update completed successfully', [
                'mpesa_diff' => $mpesaDiff,
                'company_share' => $companyShare,
                'shareholder_pool' => $shareholderPool,
            ]);

            return self::SUCCESS;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Revenue distribution failed: '.$e->getMessage());
            Log::error('balances:update failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return self::FAILURE;
        }
    }
}
