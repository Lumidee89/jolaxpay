<?php

namespace App\Enums;

/** TRD §7 — where OTP verification is required. */
enum OtpPurpose: string
{
    case Registration = 'registration';
    case NewDeviceLogin = 'new_device_login';
    case PasswordReset = 'password_reset';
    case HighValueTransaction = 'high_value_transaction';
}
