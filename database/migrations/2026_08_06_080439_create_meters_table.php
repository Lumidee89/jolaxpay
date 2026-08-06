<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saved Meters (PRD §7.2). A meter belongs to at most one group
     * (TRD §4 design notes).
     */
    public function up(): void
    {
        Schema::create('meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('disco_id')->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('meter_groups')->nullOnDelete();
            $table->string('label');
            $table->string('meter_number');
            $table->string('meter_type')->default('prepaid'); // prepaid, postpaid
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_email')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'meter_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meters');
    }
};
