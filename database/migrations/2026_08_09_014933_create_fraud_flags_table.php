<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRD §15: "basic, rules-based fraud checks on transaction velocity and
     * unusual amounts." FraudCheckService writes here — detective only, it
     * never blocks the purchase itself (see TransactionService::initiate())
     * — staff review and act on it from Admin (manage-fraud permission),
     * same "flag now, human decides" pattern as Referrals' abuse flag.
     */
    public function up(): void
    {
        Schema::create('fraud_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rule'); // velocity, unusual_amount
            $table->string('status')->default('open'); // open, reviewed, dismissed
            $table->text('details')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_flags');
    }
};
