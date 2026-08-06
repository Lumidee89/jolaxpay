<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The core ledger-adjacent record (TRD §4, §6). Every service category
     * (electricity now, airtime/data/cable later) reuses this table via
     * `service_type` rather than a table per category. Recipient info is
     * snapshotted here at purchase time so historical receipts stay
     * accurate even if the saved meter/contact is edited later.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('meter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('meter_group_id')->nullable()->constrained('meter_groups')->nullOnDelete();

            $table->string('service_type')->default('electricity'); // electricity, airtime, data, cable_tv, ...
            $table->decimal('amount', 14, 2);
            $table->decimal('fee', 14, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->decimal('fx_rate', 14, 6)->nullable(); // set when currency != NGN (Diaspora Mode)
            $table->decimal('amount_ngn', 14, 2)->nullable(); // naira-equivalent snapshot shown pre-confirmation

            // Payment flow state machine (TRD §6)
            $table->string('status')->default('fee_disclosed');
            $table->string('payment_method')->nullable(); // card, bank_transfer, ussd, apple_pay, google_pay, wallet
            $table->string('payment_reference')->nullable();

            // Vending outcome
            $table->string('token')->nullable();
            $table->unsignedTinyInteger('vend_attempts')->default(0);
            $table->unsignedTinyInteger('delivery_attempts')->default(0);
            $table->boolean('refunded_to_wallet')->default(false);

            // Direct Token Delivery (PRD §7.3)
            $table->string('delivery_destination')->default('me'); // me, meter_owner, someone_else
            $table->string('delivery_channel')->nullable(); // sms, email, whatsapp, in_app
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_email')->nullable();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();

            // Outcome Confirmation (PRD §7.6)
            $table->boolean('outcome_confirmed')->nullable();
            $table->string('outcome_reason')->nullable(); // invalid_token, meter_problem, area_outage, not_sure
            $table->timestamp('outcome_confirmed_at')->nullable();

            $table->string('idempotency_key')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['status']);
            $table->index(['service_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
