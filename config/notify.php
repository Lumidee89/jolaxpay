<?php

// Driver selection for the Notifications domain (TRD §5, §9). 'log' writes
// to the application log instead of calling a real gateway — safe default
// for local/staging until SMS/WhatsApp credentials exist.
return [
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'api_key' => env('SMS_API_KEY'),
        'sender_id' => env('SMS_SENDER_ID', 'JolaxPay'),
    ],

    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'log'),
        'api_token' => env('WHATSAPP_API_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    ],
];
