<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks a Paystack-hosted-checkout wallet top-up from initialize
     * through webhook confirmation. Wallet funding isn't a Payment Flow
     * purchase (no Transaction row — see WalletController's existing
     * comment on that), so unlike a card purchase (which threads its
     * Paystack reference through `transactions.meta`), funding needs its
     * own small record to resolve a webhook's reference back to "credit
     * which wallet, how much" once the async redirect flow completes.
     */
    public function up(): void
    {
        Schema::create('wallet_funding_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('status')->default('pending'); // pending, success, failed
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_funding_intents');
    }
};
