<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Identity, Users, RBAC, Sessions, and Sanctum tokens.
     */
    public function up(): void
    {
        // 1. Users Table
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->string('phone_number', 20)->unique();
            $table->string('email', 150)->nullable()->unique();
            $table->string('password_hash', 255)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('user_type', 30)->default('customer');
            $table->string('status', 30)->default('pending_verification');
            $table->timestampTz('phone_verified_at')->nullable();
            $table->timestampTz('email_verified_at')->nullable();
            $table->integer('failed_login_attempts')->default(0);
            $table->timestampTz('locked_until')->nullable();
            $table->rememberToken();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['user_type', 'status']);
        });

        // 2. Roles Table
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->string('name', 50)->unique();
            $table->string('display_name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_system_role')->default(false);
            $table->timestampsTz();
        });

        // 3. Permissions Table
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->string('name', 100)->unique();
            $table->string('module', 50);
            $table->string('description', 255)->nullable();
            $table->timestampsTz();

            $table->index('module');
        });

        // 4. User Roles Pivot
        Schema::create('user_roles', function (Blueprint $table) {
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->uuid('assigned_by')->nullable();
            $table->timestampTz('assigned_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->primary(['user_id', 'role_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');
        });

        // 5. Role Permissions Pivot
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->uuid('role_id');
            $table->uuid('permission_id');

            $table->primary(['role_id', 'permission_id']);
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
        });

        // 6. OTP Verifications
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->string('phone_or_email', 150);
            $table->string('purpose', 50); // registration, login, password_reset, change_phone
            $table->string('otp_hash', 255);
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->timestampTz('expires_at');
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->index(['phone_or_email', 'purpose', 'expires_at']);
        });

        // 7. Login Sessions
        Schema::create('login_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('user_id');
            $table->string('refresh_token_hash', 255)->nullable();
            $table->string('device_id', 150)->nullable();
            $table->string('device_type', 30)->nullable(); // android, ios, web
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'device_id']);
        });

        // 8. Laravel Sanctum Personal Access Tokens
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // 9. Password Reset Tokens
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('login_sessions');
        Schema::dropIfExists('otp_verifications');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
    }
};
