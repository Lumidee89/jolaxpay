<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRD §23 "AI insight engagement" — a bare shown/clicked log for the
     * Home insight card (InsightService), fed by mobile pings to
     * POST /v1/insights/engagement. Deliberately minimal: this exists
     * only to make that one analytics metric real, not a general-purpose
     * event tracker.
     */
    public function up(): void
    {
        Schema::create('insight_engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('insight_type');
            $table->string('action'); // shown, clicked
            $table->timestamp('created_at')->useCurrent();

            $table->index(['insight_type', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insight_engagements');
    }
};
