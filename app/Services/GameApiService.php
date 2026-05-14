<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GameApiService
{
    protected string $baseUrl;

    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('game_api.url');
        $this->apiKey = config('game_api.key');
    }

    protected function client(): PendingRequest
    {
        return Http::withToken($this->apiKey)->baseUrl($this->baseUrl);
    }

    public function getWallet(string $encryptedId): array
    {
        return $this->client()->get("wallets/{$encryptedId}")->json() ?? [];
    }

    public function updateWallet(string $encryptedId, float $balance): array
    {
        return $this->client()->put("wallets/{$encryptedId}", ['balance' => $balance])->json() ?? [];
    }

    public function getDeposits(): array
    {
        return $this->client()->get('transactions/deposits')->json() ?? [];
    }

    public function getWithdrawals(): array
    {
        return $this->client()->get('transactions/withdrawals')->json() ?? [];
    }

    public function getPurchases(): array
    {
        return $this->client()->get('transactions/purchases')->json() ?? [];
    }

    public function getCustomersByReferral(array $codes): array
    {
        return $this->client()->post('stats/customers/referrals', ['referral_code' => $codes])->json() ?? [];
    }

    public function getPurchasesByReferral(array $codes): array
    {
        return $this->client()->post('stats/purchases/referrals', ['referral_code' => $codes])->json() ?? [];
    }

    public function getAccountTransactions(string $encryptedId): array
    {
        return $this->client()->get("accounts/{$encryptedId}/transactions")->json() ?? [];
    }

    public function getB2CBalance(): float
    {
        $response = $this->client()->get('b2c/balance')->json();

        return (float) ($response['balance'] ?? 0);
    }
}
