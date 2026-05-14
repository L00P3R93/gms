<?php

namespace App\Services;

class MpesaService
{
    public function b2c(string $phone, float $amount, string $remarks = 'Payout'): array
    {
        // TODO: Phase 5 — port Mpesa\B2C logic here
        return [];
    }

    public function getB2CBalance(): float
    {
        // TODO: Phase 5
        return 0.00;
    }
}
