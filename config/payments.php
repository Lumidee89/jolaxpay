<?php

// Driver selection behind App\Domain\Payments\Contracts\PaymentProcessorContract.
// 'domestic' handles NGN card/bank transfer/USSD; 'international' handles
// Diaspora Mode currencies (Apple Pay/Google Pay/foreign cards) (TRD §2.2, §9).
return [
    'domestic' => [
        'driver' => env('PAYMENTS_DOMESTIC_DRIVER', 'mock'),
        'public_key' => env('PAYMENTS_DOMESTIC_PUBLIC_KEY'),
        'secret_key' => env('PAYMENTS_DOMESTIC_SECRET_KEY'),
    ],

    'international' => [
        'driver' => env('PAYMENTS_INTERNATIONAL_DRIVER', 'mock'),
        'public_key' => env('PAYMENTS_INTERNATIONAL_PUBLIC_KEY'),
        'secret_key' => env('PAYMENTS_INTERNATIONAL_SECRET_KEY'),
    ],

    'domestic_currency' => 'NGN',

    'safehaven' => [
        'base_url' => env('SAFEHAVEN_BASE_URL', 'https://api.sandbox.safehavenmfb.com'),
        'oauth_client_id' => env('SAFEHAVEN_OAUTH_CLIENT_ID'),
        'ibs_client_id' => env('SAFEHAVEN_IBS_CLIENT_ID'),
        'company_url' => env('SAFEHAVEN_COMPANY_URL'),
        'private_key' => env('SAFEHAVEN_PRIVATE_KEY_PATH'),
        'debit_account_number' => env('SAFEHAVEN_DEBIT_ACCOUNT_NUMBER'),
        'settlement_bank_code' => env('SAFEHAVEN_SETTLEMENT_BANK_CODE', '999240'),
        'webhook_url' => env('SAFEHAVEN_WEBHOOK_URL'),
        'virtual_account_ttl' => (int) env('SAFEHAVEN_VIRTUAL_ACCOUNT_TTL', 900),
        'timeout' => (int) env('SAFEHAVEN_TIMEOUT', 30),
    ],

    // Transparent, itemised convenience fee (PRD §7.9, §10) — a flat rate
    // for now; swap for a tiered/service-type-aware schedule as pricing
    // is finalised.
    'convenience_fee_rate' => env('CONVENIENCE_FEE_RATE', '0.015'),
];
