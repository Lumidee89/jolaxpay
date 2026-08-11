<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRD §13 Business Dashboard — a manual bookkeeping ledger, separate
     * from the wallet's `ledger_entries` (that one is payment money
     * movement; this one is a business account's own income/expense
     * records, entered by hand, only meaningful for
     * users.account_type = 'business').
     */
    public function up(): void
    {
        Schema::create('business_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // income, expense
            $table->string('category');
            $table->decimal('amount', 14, 2);
            $table->string('note')->nullable();
            $table->date('entry_date');
            $table->timestamps();

            $table->index(['user_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_ledger_entries');
    }
};
