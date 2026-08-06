<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Power Circle Rewards (TRD §4). Abuse/velocity limits are enforced in
     * the domain layer, not here — this table just records outcomes for
     * Admin `Referrals/Index.tsx`.
     */
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code');
            $table->string('status')->default('pending'); // pending, qualified, rewarded, flagged
            $table->string('reward_type')->nullable(); // wallet_credit, fee_waiver
            $table->decimal('reward_value', 14, 2)->nullable();
            $table->timestamps();

            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
