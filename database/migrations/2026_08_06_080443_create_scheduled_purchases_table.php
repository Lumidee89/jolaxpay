<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scheduled/recurring purchases (PRD §7.4). Evaluated by the
     * Scheduling domain's job engine, which triggers Payments + Vending
     * for every row where `next_run_at` is due (TRD §5).
     */
    public function up(): void
    {
        Schema::create('scheduled_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('frequency'); // weekly, biweekly, monthly, custom
            $table->unsignedSmallInteger('custom_interval_days')->nullable();
            $table->string('payment_method_id')->nullable();
            $table->timestamp('next_run_at');
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_purchases');
    }
};
