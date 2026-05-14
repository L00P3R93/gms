<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    protected string $env;

    protected array $b2cConfig;

    public function __construct()
    {
        $this->env = config('mpesa.env', 'sandbox');
        $this->b2cConfig = config('mpesa.b2c');
    }

    protected function baseUrl(): string
    {
        return $this->env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    protected function getAccessToken(string $consumerKey, string $consumerSecret): string
    {
        $cacheKey = "mpesa_token_{$consumerKey}";

        return Cache::remember($cacheKey, 3500, function () use ($consumerKey, $consumerSecret) {
            $response = Http::withBasicAuth($consumerKey, $consumerSecret)
                ->get($this->baseUrl().'/oauth/v1/generate?grant_type=client_credentials');

            if ($response->failed()) {
                Log::error('M-Pesa OAuth failed', ['body' => $response->body()]);
                throw new \Exception('M-Pesa authentication failed');
            }

            return $response->json('access_token');
        });
    }

    protected function encryptSecurityCredential(string $plainText): string
    {
        $certPath = $this->env === 'production'
            ? storage_path('mpesa/productionCert.pem')
            : storage_path('mpesa/sandboxCert.pem');

        $cert = file_get_contents($certPath);
        openssl_public_encrypt($plainText, $encrypted, $cert, OPENSSL_PKCS1_PADDING);

        return base64_encode($encrypted);
    }

    /**
     * Send a B2C payment to a phone number.
     *
     * @param  string  $phone  Recipient phone in 254xxxxxxxxx format
     * @param  float  $amount  Amount in KES
     * @param  string  $remarks  Short description
     * @return array Safaricom API response
     */
    public function b2c(string $phone, float $amount, string $remarks = 'Payout'): array
    {
        $token = $this->getAccessToken(
            $this->b2cConfig['consumer_key'],
            $this->b2cConfig['consumer_secret']
        );

        $payload = [
            'InitiatorName' => $this->b2cConfig['initiator_name'],
            'SecurityCredential' => $this->encryptSecurityCredential($this->b2cConfig['security_credential']),
            'CommandID' => 'BusinessPayment',
            'Amount' => (int) $amount,
            'PartyA' => $this->b2cConfig['short_code'],
            'PartyB' => $phone,
            'Remarks' => $remarks,
            'QueueTimeOutURL' => $this->b2cConfig['timeout_url'],
            'ResultURL' => $this->b2cConfig['result_url'],
            'Occasion' => '',
        ];

        $response = Http::withToken($token)
            ->post($this->baseUrl().'/mpesa/b2c/v1/paymentrequest', $payload);

        Log::info('M-Pesa B2C request', [
            'phone' => $phone,
            'amount' => $amount,
            'response' => $response->json(),
        ]);

        if ($response->failed()) {
            throw new \Exception('M-Pesa B2C request failed: '.$response->body());
        }

        return $response->json();
    }

    public function queryB2CBalance(): array
    {
        $token = $this->getAccessToken(
            $this->b2cConfig['consumer_key'],
            $this->b2cConfig['consumer_secret']
        );

        $payload = [
            'Initiator' => $this->b2cConfig['initiator_name'],
            'SecurityCredential' => $this->encryptSecurityCredential($this->b2cConfig['security_credential']),
            'CommandID' => 'AccountBalance',
            'PartyA' => $this->b2cConfig['short_code'],
            'IdentifierType' => '4',
            'Remarks' => 'Account Balance Query',
            'QueueTimeOutURL' => $this->b2cConfig['timeout_url'],
            'ResultURL' => $this->b2cConfig['result_url'],
        ];

        $response = Http::withToken($token)
            ->post($this->baseUrl().'/mpesa/accountbalance/v1/query', $payload);

        Log::info('M-Pesa AccountBalance request', ['response' => $response->json()]);

        if ($response->failed()) {
            throw new \Exception('M-Pesa AccountBalance request failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
