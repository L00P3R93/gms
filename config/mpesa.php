<?php

return [
    'env' => env('MPESA_ENV', 'sandbox'),
    'b2c' => [
        'consumer_key' => env('MPESA_B2C_CONSUMER_KEY'),
        'consumer_secret' => env('MPESA_B2C_CONSUMER_SECRET'),
        'initiator_name' => env('MPESA_B2C_INITIATOR_NAME'),
        'security_credential' => env('MPESA_B2C_SECURITY_CREDENTIAL'),
        'short_code' => env('MPESA_B2C_SHORT_CODE'),
        'result_url' => env('MPESA_B2C_RESULT_URL'),
        'timeout_url' => env('MPESA_B2C_TIMEOUT_URL'),
    ],
    'c2b' => [
        'consumer_key' => env('MPESA_C2B_CONSUMER_KEY'),
        'consumer_secret' => env('MPESA_C2B_CONSUMER_SECRET'),
        'short_code' => env('MPESA_C2B_SHORT_CODE'),
        'confirmation_url' => env('MPESA_C2B_CONFIRMATION_URL'),
        'validation_url' => env('MPESA_C2B_VALIDATION_URL'),
    ],
    'lnmo' => [
        'short_code' => env('MPESA_LNMO_SHORT_CODE'),
        'passkey' => env('MPESA_LNMO_PASSKEY'),
        'callback_url' => env('MPESA_LNMO_CALLBACK'),
    ],
];
