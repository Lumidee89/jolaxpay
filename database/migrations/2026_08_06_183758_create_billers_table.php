<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generic vendor reference table for every non-electricity service
     * (airtime, data, cable_tv, education) — the counterpart to `discos`,
     * which stays electricity-only because it's tightly coupled to
     * `meters` (meter_number/meter_type). A biller instead identifies a
     * network/provider/exam-body (MTN, DSTV, WAEC, ...) that a Transaction
     * is vended against directly, without a saved "meter"-shaped record.
     *
     * `requires_billers_code`/`requires_variation` describe what VTpass
     * needs for this specific biller's `/pay` call (airtime: neither;
     * data/cable_tv/education(jamb): both; education(waec): variation
     * only) — see VtpassBillerProvider. `health_status` feeds the same
     * Provider Health Dashboard as discos (TRD §3, §5).
     */
    public function up(): void
    {
        Schema::create('billers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('service_type'); // airtime, data, cable_tv, education
            $table->string('api_provider_code'); // VTpass serviceID
            $table->string('identifier_label')->nullable(); // e.g. "Phone number", "Smartcard number", "Profile ID" — mobile form label
            $table->boolean('requires_billers_code')->default(false);
            $table->boolean('requires_variation')->default(false);
            $table->boolean('supports_verify')->default(false);
            $table->string('health_status')->default('unknown');
            $table->timestamp('health_checked_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['service_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billers');
    }
};
