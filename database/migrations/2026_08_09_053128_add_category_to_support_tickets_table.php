<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRD §23 "support tickets broken down by category" — auto-assigned
     * from a keyword heuristic at creation time (see
     * SupportTicketController::categoryFor()) so the buyer never has to
     * pick one themselves.
     */
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('category')->default('other')->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
