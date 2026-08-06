<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Double-entry ledger backing every wallet credit/debit, including
     * refunds and referral rewards (TRD §5, Wallet & Ledger domain).
     * `wallets.balance` is a cache; this table is the source of truth —
     * every LedgerService::post() call writes a balanced pair of rows
     * (or a single row against a system contra-account) and is never
     * mutated afterward, only reversed by a new entry.
     */
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // credit, debit
            $table->string('reason'); // purchase, refund, wallet_funding, referral_reward, adjustment
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('reference')->unique();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
