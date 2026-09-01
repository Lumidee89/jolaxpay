<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Email verification is a registration requirement from this
        // release onward. Existing customers signed up under the previous
        // contract, so let them retain access and continue through the
        // established SMS new-device OTP flow at login.
        DB::table('users')
            ->whereNull('email_verified_at')
            ->whereNull('deleted_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // This is an irreversible data backfill: clearing the field here
        // would also unverify users who genuinely confirmed their email.
    }
};
