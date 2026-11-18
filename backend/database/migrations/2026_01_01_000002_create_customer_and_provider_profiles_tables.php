<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Customer and Provider profiles, location tracking, and status audit trail.
     */
    public function up(): void
    {
        // 1. Customer Profiles
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('user_id')->unique();
            $table->uuid('default_address_id')->nullable();
            $table->string('profile_photo_url', 500)->nullable();
            $table->string('preferred_language', 10)->default('en');
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 2. Provider Profiles
        Schema::create('provider_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('user_id')->unique();
            $table->string('business_name', 150)->nullable();
            $table->string('cnic_number', 20)->nullable()->unique();
            $table->text('cnic_encrypted')->nullable();
            $table->string('status', 30)->default('draft');
            $table->integer('onboarding_step')->default(1);
            $table->decimal('rating_average', 3, 2)->default(0.00);
            $table->integer('total_reviews')->default(0);
            $table->integer('completed_jobs_count')->default(0);
            $table->decimal('acceptance_rate', 5, 2)->default(100.00);
            $table->integer('response_time_seconds')->nullable();
            $table->timestampTz('location_updated_at')->nullable();
            $table->boolean('is_online')->default(false);
            $table->boolean('is_available')->default(false);
            $table->text('rejection_reason')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->uuid('suspended_by')->nullable();
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('suspended_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'is_online', 'is_available']);
        });

        // Add PostGIS Spatial Geometry Column to Provider Profiles
        DB::statement("SELECT AddGeometryColumn('public', 'provider_profiles', 'current_location', 4326, 'POINT', 2);");
        DB::statement("CREATE INDEX idx_provider_profiles_current_location ON provider_profiles USING GIST (current_location);");

        // 3. Provider Status History
        Schema::create('provider_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('provider_profile_id');
            $table->string('previous_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->text('reason')->nullable();
            $table->uuid('changed_by')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->foreign('provider_profile_id')->references('id')->on('provider_profiles')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['provider_profile_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_status_history');
        Schema::dropIfExists('provider_profiles');
        Schema::dropIfExists('customer_profiles');
    }
};
