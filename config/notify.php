<?php

// Driver selection for the Notifications domain (TRD §5, §9). 'log' writes
// to the application log instead of calling a real gateway — safe default
// for local/staging until SMS/WhatsApp credentials exist.
return [
    // 'bulksmslive' talks to BulkSMSLive.com's HTTP API (currently the
    // only real 'sms' driver — see BulkSmsLiveGateway). It supports
    // either of BulkSMSLive's two auth methods: an API key (preferred —
    // revocable without touching the account password) or the account
    // email/password. Only one is required; the API key wins if both
    // happen to be set.
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'api_key' => env('SMS_API_KEY'),
        'email' => env('SMS_EMAIL'),
        'password' => env('SMS_PASSWORD'),
        'sender_id' => env('SMS_SENDER_ID', 'JolaxPay'), // BulkSMSLive caps this at 11 characters.
    ],

    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'log'),
        'api_token' => env('WHATSAPP_API_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    ],

    'push' => [
        // Expo Push Service accepts ExpoPushToken values sent by the mobile app.
        'driver' => env('PUSH_DRIVER', 'expo'),
        'endpoint' => env('EXPO_PUSH_ENDPOINT', 'https://exp.host/--/api/v2/push/send'),
    ],
];
