<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * `wallet_address` is how another JolaxPay user sends this wallet
     * money (LedgerService::transfer()) — a stable, shareable identifier
     * that isn't the account's phone/email. Assigned once at wallet
     * creation (LedgerService::walletFor()); existing wallets are
     * backfilled here so the column can be non-nullable/unique from the
     * start rather than tolerating nulls forever.
     */
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->string('wallet_address')->nullable()->after('user_id');
        });

        DB::table('wallets')->whereNull('wallet_address')->orderBy('id')->each(function ($wallet) {
            DB::table('wallets')->where('id', $wallet->id)->update([
                'wallet_address' => $this->generateAddress(),
            ]);
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->string('wallet_address')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropUnique(['wallet_address']);
            $table->dropColumn('wallet_address');
        });
    }

    /** Mirrors LedgerService::generateWalletAddress() — kept independent since a migration must not depend on application code that may change later. */
    protected function generateAddress(): string
    {
        do {
            $candidate = 'JLX'.Str::upper(Str::random(10));
        } while (DB::table('wallets')->where('wallet_address', $candidate)->exists());

        return $candidate;
    }
};
