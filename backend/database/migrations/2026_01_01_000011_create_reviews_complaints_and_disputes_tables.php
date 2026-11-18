<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Reviews, Customer/Provider Complaints, and Formal Booking Disputes.
     */
    public function up(): void
    {
        // 1. Reviews (Two-way ratings between customer and provider)
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('booking_id');
            $table->uuid('reviewer_user_id');
            $table->uuid('reviewee_user_id');
            $table->tinyInteger('rating'); // 1 to 5
            $table->text('comment')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestampsTz();

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('reviewer_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewee_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['booking_id', 'reviewer_user_id']);
            $table->index(['reviewee_user_id', 'rating']);
        });

        // 2. Complaints (Support tickets)
        Schema::create('complaints', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('booking_id')->nullable();
            $table->uuid('complainant_user_id');
            $table->string('subject', 200);
            $table->text('description');
            $table->string('priority', 20)->default('medium'); // low, medium, high, urgent
            $table->string('status', 30)->default('open'); // open, under_investigation, resolved, closed
            $table->uuid('resolved_by')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestampsTz();

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('set null');
            $table->foreign('complainant_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['status', 'priority']);
        });

        // 3. Disputes (Financial / Service delivery disputes on bookings)
        Schema::create('disputes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('booking_id');
            $table->uuid('raised_by_user_id');
            $table->text('dispute_reason');
            $table->string('dispute_status', 30)->default('opened'); // opened, under_review, resolved_refund, resolved_payout, resolved_split, rejected
            $table->decimal('customer_claim_amount', 12, 2)->default(0.00);
            $table->decimal('provider_counter_claim_amount', 12, 2)->default(0.00);
            $table->decimal('resolved_refund_amount', 12, 2)->default(0.00);
            $table->decimal('resolved_payout_amount', 12, 2)->default(0.00);
            $table->uuid('arbitrator_user_id')->nullable();
            $table->text('arbitration_notes')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('raised_by_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('arbitrator_user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['booking_id', 'dispute_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('reviews');
    }
};
