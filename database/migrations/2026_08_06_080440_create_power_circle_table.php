<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Power Circle (PRD §7.2) — trusted people/organisations a user buys
     * electricity for, independent of (but attachable to) a meter.
     */
    public function up(): void
    {
        Schema::create('power_circle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('contact_name');
            $table->string('relationship')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();
            $table->foreignId('linked_meter_id')->nullable()->constrained('meters')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('power_circle');
    }
};
