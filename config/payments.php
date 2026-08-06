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

    // Transparent, itemised convenience fee (PRD §7.9, §10) — a flat rate
    // for now; swap for a tiered/service-type-aware schedule as pricing
    // is finalised.
    'convenience_fee_rate' => env('CONVENIENCE_FEE_RATE', '0.015'),
];
