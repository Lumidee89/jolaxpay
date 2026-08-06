<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reference table for electricity distribution companies (and, longer
     * term, any vending-backed provider). `health_status` feeds the
     * Provider Health Dashboard and `/v1/providers/status` (TRD §3, §5).
     */
    public function up(): void
    {
        Schema::create('discos', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('region')->nullable();
            $table->string('service_type')->default('electricity');
            $table->string('api_provider_code')->nullable();
            $table->string('health_status')->default('unknown');
            $table->timestamp('health_checked_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discos');
    }
};
