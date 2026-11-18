<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Customer addresses (with ownership constraint), provider verification documents, and bank accounts.
     */
    public function up(): void
    {
        // 1. Customer Addresses
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('customer_profile_id');
            $table->string('label', 50)->default('Home'); // Home, Office, Other
            $table->text('address_line_1');
            $table->text('address_line_2')->nullable();
            $table->string('area', 100);
            $table->string('city', 100);
            $table->string('postal_code', 20)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestampsTz();

            $table->foreign('customer_profile_id')->references('id')->on('customer_profiles')->onDelete('cascade');
            
            // CRITICAL FOR ADDRESS OWNERSHIP ENFORCEMENT:
            // Allows foreign key from bookings(address_id, customer_profile_id)
            $table->unique(['id', 'customer_profile_id'], 'uq_customer_address_owner');
        });

        // Add PostGIS Spatial Geometry Column to Customer Addresses
        DB::statement("SELECT AddGeometryColumn('public', 'customer_addresses', 'location', 4326, 'POINT', 2);");
        DB::statement("CREATE INDEX idx_customer_addresses_location ON customer_addresses USING GIST (location);");

        // Add foreign key from customer_profiles.default_address_id now that customer_addresses exists
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->foreign('default_address_id')->references('id')->on('customer_addresses')->onDelete('set null');
        });

        // 2. Provider Documents
        Schema::create('provider_documents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('provider_profile_id');
            $table->string('document_type', 50); // cnic_front, cnic_back, police_clearance, trade_license, cert
            $table->string('document_number', 100)->nullable();
            $table->string('file_path_s3', 500);
            $table->string('file_mime_type', 100)->nullable();
            $table->string('verification_status', 30)->default('pending'); // pending, verified, rejected
            $table->uuid('verified_by')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestampsTz();

            $table->foreign('provider_profile_id')->references('id')->on('provider_profiles')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['provider_profile_id', 'document_type', 'verification_status']);
        });

        // 3. Provider Bank Accounts
        Schema::create('provider_bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('provider_profile_id');
            $table->string('bank_name', 100);
            $table->string('account_title', 150);
            $table->text('account_number_encrypted');
            $table->text('iban_encrypted');
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_primary')->default(true);
            $table->uuid('verified_by')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();

            $table->foreign('provider_profile_id')->references('id')->on('provider_profiles')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->dropForeign(['default_address_id']);
        });

        Schema::dropIfExists('provider_bank_accounts');
        Schema::dropIfExists('provider_documents');
        Schema::dropIfExists('customer_addresses');
    }
};
