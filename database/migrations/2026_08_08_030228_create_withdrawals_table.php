<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wallet -> bank account payout via Paystack Transfers. The wallet is
     * debited immediately on request (held, same "debit now, refund on
     * failure" pattern as a purchase — see LedgerReason::Withdrawal /
     * ::WithdrawalReversal); Paystack transfers always come back "pending"
     * first and resolve via `transfer.success`/`transfer.failed` webhook
     * events (https://paystack.com — Sending Money docs), never
     * synchronously, so `status` starts pending here too.
     */
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('bank_code');
            $table->string('bank_name')->nullable();
            $table->string('account_number');
            $table->string('account_name')->nullable();
            $table->string('paystack_recipient_code')->nullable();
            $table->string('paystack_transfer_code')->nullable();
            $table->string('reference')->unique();
            $table->string('status')->default('pending'); // pending, success, failed
            $table->string('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
