<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saved recipient for a non-electricity biller (PRD §7.2's "Saved
     * Meters" pattern, generalized) — a phone number for airtime/data, a
     * smartcard number for cable_tv, a profile ID for JAMB. The
     * electricity equivalent stays `meters` because it carries
     * electricity-specific columns (meter_type); this is the lean,
     * shared shape everything else needs.
     */
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('biller_id')->constrained()->restrictOnDelete();
            $table->string('label');
            $table->string('identifier'); // phone / smartcard number / profile ID
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_email')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'biller_id', 'identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
    }
};
