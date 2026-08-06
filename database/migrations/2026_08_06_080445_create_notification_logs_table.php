<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dispatch log for the Notifications domain (TRD §4/§5), covering SMS,
     * email, WhatsApp, and in-app delivery — including Outcome Confirmation
     * prompts. Named `notification_logs` (not `notifications`) to avoid
     * colliding with Laravel's built-in database notifications channel,
     * which is not used here.
     */
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // token_delivery, outcome_prompt, delay_alert, scheduled_reminder, ...
            $table->string('channel'); // sms, email, whatsapp, in_app
            $table->json('payload')->nullable();
            $table->string('status')->default('queued'); // queued, sent, failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
