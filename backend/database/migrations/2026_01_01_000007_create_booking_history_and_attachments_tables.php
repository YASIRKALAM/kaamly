<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Booking status audit transitions and photo/document attachments.
     */
    public function up(): void
    {
        // 1. Booking Status History
        Schema::create('booking_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('booking_id');
            $table->string('previous_status', 40)->nullable();
            $table->string('new_status', 40);
            $table->text('reason')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->uuid('changed_by')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['booking_id', 'created_at']);
        });

        // 2. Booking Attachments (Before/After photos, invoices, inspection photos)
        Schema::create('booking_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('booking_id');
            $table->string('file_type', 50); // before_work, after_work, invoice, dispute_evidence
            $table->string('file_path_s3', 500);
            $table->string('file_mime_type', 100)->nullable();
            $table->bigInteger('file_size_bytes')->nullable();
            $table->uuid('uploaded_by')->nullable();
            $table->timestampsTz();

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['booking_id', 'file_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_attachments');
        Schema::dropIfExists('booking_status_history');
    }
};
