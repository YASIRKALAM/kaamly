<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Bookings, Booking Requests, Items, Quotations, and quotation line items.
     * Enforces customer address ownership and GiST exclusion double-booking prevention.
     */
    public function up(): void
    {
        // 1. Booking Requests (Dispatch / Broadcast intent)
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('customer_profile_id');
            $table->uuid('service_id');
            $table->uuid('address_id');
            $table->timestampTz('requested_schedule_start');
            $table->timestampTz('requested_schedule_end');
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('pending'); // pending, broadcasted, accepted, expired, cancelled
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->foreign('customer_profile_id')->references('id')->on('customer_profiles')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('restrict');
            $table->foreign('address_id')->references('id')->on('customer_addresses')->onDelete('restrict');
            $table->index(['customer_profile_id', 'status']);
        });

        // 2. Bookings Table
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->string('booking_number', 30)->unique();
            $table->uuid('customer_profile_id');
            $table->uuid('provider_profile_id')->nullable();
            $table->uuid('booking_request_id')->nullable();
            $table->uuid('address_id');
            $table->uuid('service_id');

            // 13-State Lifecycle Status
            $table->string('status', 40)->default('pending');
            $table->string('payment_mode', 30)->default('online_escrow');
            $table->string('payment_status', 30)->default('pending');

            // Schedules
            $table->timestampTz('scheduled_start_at');
            $table->timestampTz('scheduled_end_at');
            $table->timestampTz('actual_started_at')->nullable();
            $table->timestampTz('actual_completed_at')->nullable();

            // Financial Agreed Snapshot
            $table->decimal('base_price', 12, 2)->default(0.00);
            $table->decimal('additional_charges', 12, 2)->default(0.00);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('commission_rate', 5, 2)->default(10.00);
            $table->decimal('platform_fee', 12, 2)->default(0.00);
            $table->decimal('agreed_total_price', 12, 2)->default(0.00);

            // Cancellation, Reschedule & No-Show Attributes
            $table->text('cancellation_reason')->nullable();
            $table->uuid('cancelled_by')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->decimal('cancellation_fee', 12, 2)->default(0.00);

            $table->string('no_show_by', 20)->nullable(); // customer, provider
            $table->string('no_show_responsible_party', 20)->nullable(); // customer, provider (alias)
            $table->text('no_show_reason')->nullable();
            $table->jsonb('no_show_evidence')->nullable(); // call logs, GPS timestamps, photos
            $table->uuid('no_show_recorded_by')->nullable();
            $table->timestampTz('no_show_recorded_at')->nullable();

            $table->uuid('reschedule_requested_by')->nullable();
            $table->text('reschedule_reason')->nullable();
            $table->timestampTz('rescheduled_start_at')->nullable();
            $table->timestampTz('rescheduled_end_at')->nullable();

            $table->timestampsTz();

            // Basic Foreign Keys
            $table->foreign('customer_profile_id')->references('id')->on('customer_profiles')->onDelete('restrict');
            $table->foreign('provider_profile_id')->references('id')->on('provider_profiles')->onDelete('restrict');
            $table->foreign('booking_request_id')->references('id')->on('booking_requests')->onDelete('set null');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('restrict');
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('reschedule_requested_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('no_show_recorded_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['customer_profile_id', 'status']);
            $table->index(['provider_profile_id', 'status', 'scheduled_start_at']);
        });

        // 2a. Address Ownership Integrity Constraint
        // Ensures customer can only book using addresses they own!
        DB::statement("ALTER TABLE bookings 
            ADD CONSTRAINT fk_booking_customer_address 
            FOREIGN KEY (address_id, customer_profile_id) 
            REFERENCES customer_addresses(id, customer_profile_id) 
            ON DELETE RESTRICT;");

        // 2b. Scheduled Range Generated Column and GiST Exclusion Constraint
        // Prevents double-booking overlapping time ranges for any confirmed/active/rescheduled provider job!
        DB::statement("ALTER TABLE bookings 
            ADD COLUMN scheduled_range tstzrange 
            GENERATED ALWAYS AS (tstzrange(scheduled_start_at, scheduled_end_at, '[)')) STORED;");

        DB::statement("ALTER TABLE bookings 
            ADD CONSTRAINT ex_bookings_no_overlap 
            EXCLUDE USING GIST (
                provider_profile_id WITH =, 
                scheduled_range WITH &&
            ) 
            WHERE (provider_profile_id IS NOT NULL AND status IN ('accepted', 'confirmed', 'provider_on_the_way', 'in_progress', 'rescheduled'));");

        // 3. Booking Items
        Schema::create('booking_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('booking_id');
            $table->uuid('service_id')->nullable();
            $table->string('description', 255);
            $table->decimal('unit_price', 12, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('total_price', 12, 2);
            $table->timestampsTz();

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('set null');
        });

        // 4. Quotations (For inspection-based workflows)
        Schema::create('quotations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('booking_id');
            $table->uuid('provider_profile_id');
            $table->integer('version')->default(1);
            $table->text('inspection_notes')->nullable();
            $table->decimal('labor_cost', 12, 2)->default(0.00);
            $table->decimal('material_cost', 12, 2)->default(0.00);
            $table->decimal('total_quote_amount', 12, 2);
            $table->string('status', 30)->default('submitted'); // submitted, accepted, rejected, superseded, expired
            $table->timestampTz('expires_at');
            $table->text('customer_rejected_reason')->nullable();
            $table->timestampsTz();

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('provider_profile_id')->references('id')->on('provider_profiles')->onDelete('restrict');
            $table->index(['booking_id', 'status']);
        });

        // 5. Quotation Line Items
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('quotation_id');
            $table->string('description', 255);
            $table->string('item_type', 30)->default('labor'); // labor, material, equipment
            $table->decimal('unit_price', 12, 2);
            $table->decimal('quantity', 8, 2)->default(1.00);
            $table->decimal('total_price', 12, 2);
            $table->timestampsTz();

            $table->foreign('quotation_id')->references('id')->on('quotations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('booking_items');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('booking_requests');
    }
};
