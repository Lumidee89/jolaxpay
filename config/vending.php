<?php

// Driver selection behind App\Domain\Vending\Contracts\VendingProviderContract
// (TRD §5, §9). Add a real driver per DisCo/telecom as integrations land;
// 'mock' lets the purchase flow be built and tested before any live
// provider credentials exist (Implementation Plan §2, Phase 1 workstream).
return [
    'electricity' => [
        'driver' => env('VENDING_ELECTRICITY_DRIVER', 'mock'),
        'base_url' => env('VENDING_ELECTRICITY_BASE_URL'),
        'api_key' => env('VENDING_ELECTRICITY_API_KEY'),
        'api_secret' => env('VENDING_ELECTRICITY_API_SECRET'),
    ],

    // Bounded retry count before the Payment Flow falls back to a wallet
    // refund (TRD §6).
    'max_vend_attempts' => 3,
    'max_delivery_attempts' => 3,
];
