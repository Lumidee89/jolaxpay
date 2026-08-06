<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Electricity transactions are anchored to `meter_id`; every other
     * service anchors to a `biller_id` (+ optionally a saved
     * `beneficiary_id`) plus whatever VTpass needs to vend it:
     * `biller_identifier` (billersCode — phone/smartcard/profile ID) and
     * `variation_code` (bundle/bouquet/pin-type selection).
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('biller_id')->nullable()->after('meter_group_id')->constrained()->nullOnDelete();
            $table->foreignId('beneficiary_id')->nullable()->after('biller_id')->constrained()->nullOnDelete();
            $table->string('biller_identifier')->nullable()->after('beneficiary_id');
            $table->string('variation_code')->nullable()->after('biller_identifier');

            $table->index(['biller_id']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('biller_id');
            $table->dropConstrainedForeignId('beneficiary_id');
            $table->dropColumn(['biller_identifier', 'variation_code']);
        });
    }
};
