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

    // Paystack (https://paystack.com/docs/api/) — the real domestic
    // processor behind PAYMENTS_DOMESTIC_DRIVER=paystack. Unlike the
    // synchronous mock/PaymentProcessorContract flow, Paystack's checkout
    // is a hosted-page redirect confirmed asynchronously (verify poll +
    // webhook) — see App\Domain\Payments\PaystackGateway, used directly by
    // WalletController/TransactionService/WithdrawalController rather than
    // through PaymentManager. Test and live keys are separate; get both
    // from the Paystack dashboard's API Keys & Webhooks settings.
    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'callback_url' => env('PAYSTACK_CALLBACK_URL'),
        'timeout' => (int) env('PAYSTACK_TIMEOUT', 30),
    ],

    // Transparent, itemised convenience fee (PRD §7.9, §10) — a flat rate
    // for now; swap for a tiered/service-type-aware schedule as pricing
    // is finalised.
    'convenience_fee_rate' => env('CONVENIENCE_FEE_RATE', '0.015'),
];
