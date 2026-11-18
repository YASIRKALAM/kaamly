<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Payment attempts, webhooks, provider payouts/settlements, and customer refunds.
     */
    public function up(): void
    {
        // 1. Payment Attempts
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('booking_id');
            $table->string('gateway_name', 50); // stripe, jazzcash, easypaisa, bank_transfer
            $table->string('gateway_reference', 150)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('PKR');
            $table->string('status', 30)->default('initiated'); // initiated, succeeded, failed, refunded
            $table->text('failure_reason')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestampsTz();

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->index(['booking_id', 'status']);
        });

        // 2. Payment Webhook Events (Idempotent Webhook Processing)
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->string('gateway_name', 50);
            $table->string('event_type', 100);
            $table->string('event_id', 150)->unique();
            $table->jsonb('raw_payload');
            $table->timestampTz('processed_at')->nullable();
            $table->string('status', 30)->default('received'); // received, processed, failed, ignored
            $table->text('failure_message')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->index(['gateway_name', 'status']);
        });

        // 3. Settlements (Provider Payout Batches)
        Schema::create('settlements', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->string('settlement_number', 40)->unique();
            $table->uuid('provider_profile_id');
            $table->uuid('provider_bank_account_id');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('PKR');
            $table->string('status', 30)->default('pending'); // pending, processing, completed, failed
            $table->string('payout_reference', 150)->nullable();
            $table->uuid('processed_by')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();

            $table->foreign('provider_profile_id')->references('id')->on('provider_profiles')->onDelete('restrict');
            $table->foreign('provider_bank_account_id')->references('id')->on('provider_bank_accounts')->onDelete('restrict');
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['provider_profile_id', 'status']);
        });

        // 4. Settlement Items (Detail lines reconciling bookings)
        Schema::create('settlement_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('settlement_id');
            $table->uuid('booking_id');
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('commission_deducted', 12, 2);
            $table->decimal('penalty_deducted', 12, 2)->default(0.00);
            $table->decimal('net_payout_amount', 12, 2);
            $table->timestampsTz();

            $table->foreign('settlement_id')->references('id')->on('settlements')->onDelete('cascade');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('restrict');
            $table->unique(['settlement_id', 'booking_id']);
        });

        // 5. Customer Refunds
        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('booking_id');
            $table->uuid('payment_attempt_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->string('status', 30)->default('requested'); // requested, approved, rejected, processed
            $table->string('gateway_refund_id', 150)->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('payment_attempt_id')->references('id')->on('payment_attempts')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['booking_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('settlement_items');
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_attempts');
    }
};
