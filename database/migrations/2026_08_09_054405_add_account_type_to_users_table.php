<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRD §13 Business Dashboard — chosen at registration
     * (RegisterRequest/AuthController::register), 'individual' by
     * default. Gates access to the /business/* endpoints
     * (BusinessLedgerController) and the mobile Business Dashboard screen.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_type')->default('individual')->after('is_diaspora');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('account_type');
        });
    }
};
