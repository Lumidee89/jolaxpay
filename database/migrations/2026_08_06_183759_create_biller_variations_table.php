<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cached copy of VTpass's GET /service-variations for billers that
     * need one (data bundles, cable TV bouquets, education pin types) —
     * see App\Console\Commands\SyncBillerVariations. Cached rather than
     * fetched live on every mobile screen load, and gives the admin/ops
     * side a place to see exactly what a customer could have picked.
     */
    public function up(): void
    {
        Schema::create('biller_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biller_id')->constrained()->cascadeOnDelete();
            $table->string('variation_code');
            $table->string('name');
            $table->decimal('amount', 14, 2);
            $table->boolean('fixed_price')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['biller_id', 'variation_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biller_variations');
    }
};
