<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Provider working shifts, day-offs, availability exceptions, service areas, and spatial coverage zones.
     */
    public function up(): void
    {
        // 1. Provider Working Hours (Recurring Weekly Shift)
        Schema::create('provider_working_hours', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('provider_profile_id');
            $table->smallInteger('day_of_week'); // 0 = Sunday, 1 = Monday ... 6 = Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->foreign('provider_profile_id')->references('id')->on('provider_profiles')->onDelete('cascade');
            $table->index(['provider_profile_id', 'day_of_week', 'is_active']);
        });

        // 2. Provider Day Off Schedules
        Schema::create('provider_day_off_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('provider_profile_id');
            $table->date('off_date');
            $table->string('reason', 255)->nullable();
            $table->timestampsTz();

            $table->foreign('provider_profile_id')->references('id')->on('provider_profiles')->onDelete('cascade');
            $table->unique(['provider_profile_id', 'off_date']);
        });

        // 3. Provider Availability Exceptions (Custom dates or adjusted hours)
        Schema::create('provider_availability_exceptions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('provider_profile_id');
            $table->date('exception_date');
            $table->boolean('is_available')->default(false);
            $table->time('custom_start_time')->nullable();
            $table->time('custom_end_time')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestampsTz();

            $table->foreign('provider_profile_id')->references('id')->on('provider_profiles')->onDelete('cascade');
            $table->unique(['provider_profile_id', 'exception_date']);
        });

        // 4. Service Areas (Municipalities / Administrative Boundaries)
        Schema::create('service_areas', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->string('name', 100);
            $table->string('city', 100);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        // Add PostGIS Spatial MultiPolygon Geometry Column to Service Areas
        DB::statement("SELECT AddGeometryColumn('public', 'service_areas', 'boundary', 4326, 'MULTIPOLYGON', 2);");
        DB::statement("CREATE INDEX idx_service_areas_boundary ON service_areas USING GIST (boundary);");

        // 5. Provider Service Zones Pivot
        Schema::create('provider_service_zones', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('provider_profile_id');
            $table->uuid('service_area_id');
            $table->decimal('custom_radius_km', 5, 2)->default(15.00);
            $table->timestampsTz();

            $table->foreign('provider_profile_id')->references('id')->on('provider_profiles')->onDelete('cascade');
            $table->foreign('service_area_id')->references('id')->on('service_areas')->onDelete('cascade');
            $table->unique(['provider_profile_id', 'service_area_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_service_zones');
        Schema::dropIfExists('service_areas');
        Schema::dropIfExists('provider_availability_exceptions');
        Schema::dropIfExists('provider_day_off_schedules');
        Schema::dropIfExists('provider_working_hours');
    }
};
