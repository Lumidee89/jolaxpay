<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('provider_transfer_id')->nullable()->after('account_name');
            $table->dropColumn(['paystack_recipient_code', 'paystack_transfer_code']);
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('paystack_recipient_code')->nullable();
            $table->string('paystack_transfer_code')->nullable();
            $table->dropColumn('provider_transfer_id');
        });
    }
};
