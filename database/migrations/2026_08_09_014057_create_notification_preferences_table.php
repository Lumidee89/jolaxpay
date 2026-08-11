<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRD §14: "a user can adjust channel preferences per notification
     * category in Settings." One row per user; `muted_categories` lists
     * the App\Enums\NotificationCategory values they've turned off.
     * Absence from the list (including a user with no row at all) means
     * enabled — see NotificationDispatcher::isMuted(). OTP codes are never
     * gated by this (mandatory 2FA, not a preference).
     */
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('muted_categories')->default('[]');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
