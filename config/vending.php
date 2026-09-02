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

    // The other four service types (PRD §7.1 Phase 2) are all
    // biller-anchored (App\Models\Biller) rather than meter-anchored, and
    // all route through the single generic VtpassBillerProvider — VTpass
    // covers airtime/data/cable_tv/education under the same account and
    // `/pay` shape (see App\Domain\Vending\Providers\VtpassBillerProvider),
    // so each only needs its own driver toggle, not its own credential set.
    'airtime' => ['driver' => env('VENDING_AIRTIME_DRIVER', 'mock')],
    'data' => ['driver' => env('VENDING_DATA_DRIVER', 'mock')],
    'cable_tv' => ['driver' => env('VENDING_CABLE_TV_DRIVER', 'mock')],
    'education' => ['driver' => env('VENDING_EDUCATION_DRIVER', 'mock')],

    // Schedwave VTU API: airtime, data, cable TV, electricity and exam PINs.
    // One server-side Bearer key covers every category.
    'schedwave' => [
        'base_url' => env('SCHEDWAVE_BASE_URL', 'https://schedwave.com/api/v1'),
        'api_key' => env('SCHEDWAVE_API_KEY'),
        'timeout' => (int) env('SCHEDWAVE_TIMEOUT', 30),
        'plan_markup' => (string) env('SCHEDWAVE_PLAN_MARKUP', '50.00'),
    ],

    // MoreValue Digital: https://morevaluedigital.com.ng/api-docs.html
    // Live data/cable plan IDs are confidential and are stored as each
    // BillerVariation::variation_code rather than fetched from an API.
    'morevalue' => [
        'base_url' => env('MOREVALUE_BASE_URL', 'https://morevaluedigital.com.ng/api'),
        'api_token' => env('MOREVALUE_API_TOKEN'),
        'timeout' => (int) env('MOREVALUE_TIMEOUT', 30),
        'electricity_providers' => [
            'IKEDC' => env('MOREVALUE_IKEDC_PROVIDER_ID', '1'),
            'EKEDC' => env('MOREVALUE_EKEDC_PROVIDER_ID', '2'),
            'AEDC' => env('MOREVALUE_AEDC_PROVIDER_ID'),
            'PHED' => env('MOREVALUE_PHED_PROVIDER_ID'),
            'KEDCO' => env('MOREVALUE_KEDCO_PROVIDER_ID'),
            'EEDC' => env('MOREVALUE_EEDC_PROVIDER_ID'),
            'IBEDC' => env('MOREVALUE_IBEDC_PROVIDER_ID', '6'),
            'BEDC' => env('MOREVALUE_BEDC_PROVIDER_ID'),
            'JED' => env('MOREVALUE_JED_PROVIDER_ID'),
            'KAEDCO' => env('MOREVALUE_KAEDCO_PROVIDER_ID'),
        ],
    ],

    // VTpass (https://vtpass.com/documentation/) — the live electricity
    // vending driver. 'sandbox' and 'live' are fully separate accounts with
    // separate keys; VTPASS_ENV picks which base URL + key set is active.
    // Sandbox has its own pre-loaded wallet balance and behaves like the
    // live API (per VTpass's own docs), so it's safe to point
    // VENDING_ELECTRICITY_DRIVER=vtpass at it for real end-to-end testing.
    'vtpass' => [
        'env' => env('VTPASS_ENV', 'sandbox'), // sandbox | live
        'base_url_sandbox' => 'https://sandbox.vtpass.com/api',
        'base_url_live' => 'https://vtpass.com/api',
        'api_key' => env('VTPASS_API_KEY'),
        'public_key' => env('VTPASS_PUBLIC_KEY'), // GET requests: api-key + public-key
        'secret_key' => env('VTPASS_SECRET_KEY'), // POST requests: api-key + secret-key
        'timeout' => (int) env('VTPASS_TIMEOUT', 30),
    ],

    // Bounded retry count before the Payment Flow falls back to a wallet
    // refund (TRD §6). For the vtpass driver, a retry past the first
    // attempt requeries the same VTpass request_id rather than re-paying
    // (see VtpassElectricityProvider) — VTpass's own guidance is to requery
    // a "pending" transaction rather than resubmit it.
    'max_vend_attempts' => 3,
    'max_delivery_attempts' => 3,
];
