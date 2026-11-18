<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Service catalog, provider service associations, custom pricing, and price approval audit history.
     */
    public function up(): void
    {
        // 1. Service Categories
        Schema::create('service_categories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('parent_id')->nullable();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('icon_url', 500)->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->foreign('parent_id')->references('id')->on('service_categories')->onDelete('set null');
        });

        // 2. Services
        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('category_id');
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->text('description')->nullable();
            $table->string('pricing_model', 30)->default('fixed'); // fixed, hourly, inspection_quote
            $table->decimal('base_price', 12, 2)->default(0.00);
            $table->decimal('minimum_price', 12, 2)->default(0.00);
            $table->decimal('maximum_price', 12, 2)->default(1000000.00);
            $table->integer('estimated_duration_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->foreign('category_id')->references('id')->on('service_categories')->onDelete('cascade');
            $table->index(['category_id', 'is_active']);
        });

        // 3. Provider Services Pivot
        Schema::create('provider_services', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('provider_profile_id');
            $table->uuid('service_id');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->foreign('provider_profile_id')->references('id')->on('provider_profiles')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->unique(['provider_profile_id', 'service_id']);
        });

        // 4. Provider Service Prices
        Schema::create('provider_service_prices', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('provider_service_id');
            $table->decimal('custom_price', 12, 2);
            $table->string('approval_status', 30)->default('pending'); // pending, approved, rejected
            $table->uuid('approved_by')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestampTz('effective_from')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('effective_to')->nullable();
            $table->timestampsTz();

            $table->foreign('provider_service_id')->references('id')->on('provider_services')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['provider_service_id', 'is_current', 'approval_status']);
        });

        // 5. Price Approval History
        Schema::create('price_approval_history', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('provider_service_price_id');
            $table->decimal('requested_price', 12, 2);
            $table->decimal('previous_price', 12, 2)->nullable();
            $table->string('action', 30); // requested, approved, rejected
            $table->uuid('reviewed_by')->nullable();
            $table->text('comments')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->foreign('provider_service_price_id')->references('id')->on('provider_service_prices')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['provider_service_price_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_approval_history');
        Schema::dropIfExists('provider_service_prices');
        Schema::dropIfExists('provider_services');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
    }
};
