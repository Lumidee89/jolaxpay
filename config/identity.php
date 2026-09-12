<?php

// Identity domain (TRD §5, §7) — settings for OTP-gated flows.
return [
    // Skips the new-device login OTP challenge entirely, so login always
    // succeeds immediately regardless of device recognition — a temporary
    // escape hatch for use before a real SMS_DRIVER is configured (with
    // SMS_DRIVER=log, the OTP code only ever reaches storage/logs/
    // laravel.log, which blocks anyone testing from a real device without
    // server access). Password reset and high-value-transaction OTP
    // challenges are untouched — this only affects login. Flip back to
    // false (the default) the moment a real SMS_DRIVER is wired up; this
    // is meant to be temporary, not a permanent way to run without 2FA.
    'bypass_login_otp' => env('AUTH_BYPASS_LOGIN_OTP', false),

    // A successful login OTP is trusted for this rolling window. Logging
    // out (including the mobile inactivity timeout) does not trigger more
    // SMS challenges until the 24-hour window expires.
    'login_otp_trust_hours' => (int) env('LOGIN_OTP_TRUST_HOURS', 24),

    // PRD §15: purchases at or above this amount (NGN) require an OTP
    // step-up before TransactionService::initiate() runs — see
    // TransactionController::store()'s use of OtpPurpose::HighValueTransaction.
    'high_value_threshold' => (float) env('HIGH_VALUE_TRANSACTION_THRESHOLD', 50000),
];
