<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('account_type', 'business')->update(['account_type' => 'agent']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 32)->nullable()->unique()->after('account_type');
            $table->timestamp('agent_approved_at')->nullable()->after('referral_code');
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->timestamp('attributed_at')->nullable()->after('status');
            $table->timestamp('activated_at')->nullable()->after('attributed_at');
            $table->foreignId('attribution_changed_by')->nullable()->after('activated_at')->constrained('users')->nullOnDelete();
            $table->text('attribution_note')->nullable()->after('attribution_changed_by');
            $table->unique('referred_user_id');
        });

        // Preserve historical attribution in period reports after introducing
        // the explicit attribution timestamp.
        DB::table('referrals')
            ->whereNull('attributed_at')
            ->update(['attributed_at' => DB::raw('created_at')]);

        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('earning_type'); // direct, referral
            $table->string('service_type')->nullable();
            $table->foreignId('biller_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('disco_id')->nullable()->constrained()->nullOnDelete();
            $table->string('calculation_type'); // fixed, percentage
            $table->decimal('value', 14, 4);
            $table->decimal('jolaxpay_margin', 14, 2)->nullable();
            $table->decimal('minimum_commission', 14, 2)->nullable();
            $table->decimal('maximum_commission', 14, 2)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('agent_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('commission_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('earning_type');
            $table->decimal('amount', 14, 2);
            $table->string('status')->default('available'); // pending, available, paid, reversed
            $table->foreignId('reversal_of_id')->nullable()->constrained('agent_commissions')->nullOnDelete();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['transaction_id', 'agent_id', 'earning_type']);
            $table->index(['agent_id', 'status', 'created_at']);
        });

        Schema::create('referral_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('leaderboard_enabled')->default(true);
            $table->string('ranking_metric')->default('active');
            $table->unsignedInteger('active_min_transactions')->default(1);
            $table->unsignedInteger('visible_positions')->default(10);
            $table->string('ranking_period')->default('monthly');
            $table->json('milestones')->nullable();
            $table->text('promotional_message')->nullable();
            $table->timestamps();
        });

        Schema::create('referral_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('ranking_metric')->default('active');
            $table->boolean('is_active')->default(false);
            $table->text('promotional_message')->nullable();
            $table->json('reward_details')->nullable();
            $table->timestamps();
        });

        Schema::create('agent_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->unsignedInteger('threshold');
            $table->timestamp('unlocked_at');
            $table->timestamps();
            $table->unique(['agent_id', 'key']);
        });

        Schema::create('agent_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('referral_campaigns')->nullOnDelete();
            $table->foreignId('issued_by')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('planned'); // planned, rewarded, cancelled
            $table->string('period_key')->nullable();
            $table->text('reward')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamps();
        });

        DB::table('referral_settings')->insert([
            'leaderboard_enabled' => true,
            'ranking_metric' => 'active',
            'active_min_transactions' => 1,
            'visible_positions' => 10,
            'ranking_period' => 'monthly',
            'milestones' => json_encode([
                ['threshold' => 10, 'name' => 'Rising Agent'],
                ['threshold' => 25, 'name' => 'Referral Builder'],
                ['threshold' => 50, 'name' => 'Referral Champion'],
                ['threshold' => 100, 'name' => 'Referral Master'],
                ['threshold' => 250, 'name' => 'Growth Leader'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_rewards');
        Schema::dropIfExists('agent_achievements');
        Schema::dropIfExists('referral_campaigns');
        Schema::dropIfExists('referral_settings');
        Schema::dropIfExists('agent_commissions');
        Schema::dropIfExists('commission_rules');
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropUnique(['referred_user_id']);
            $table->dropConstrainedForeignId('attribution_changed_by');
            $table->dropColumn(['attributed_at', 'activated_at', 'attribution_note']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['referral_code']);
            $table->dropColumn(['referral_code', 'agent_approved_at']);
        });
        DB::table('users')->where('account_type', 'agent')->update(['account_type' => 'business']);
    }
};
