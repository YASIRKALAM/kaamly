<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Notifications, preferences, system configurations, and immutable audit logs.
     */
    public function up(): void
    {
        // 1. Notifications Table
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('user_id');
            $table->string('title', 200);
            $table->text('body');
            $table->jsonb('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestampTz('read_at')->nullable();
            $table->string('channel', 30)->default('in_app'); // push, sms, in_app, email
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'is_read', 'created_at']);
        });

        // 2. Notification Preferences
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('user_id');
            $table->string('channel', 30); // push, sms, email
            $table->string('event_type', 50); // booking_update, chat_message, promotion
            $table->boolean('is_enabled')->default(true);
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'channel', 'event_type']);
        });

        // 3. System Configurations (Dynamic platform parameters)
        Schema::create('system_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->string('key', 100)->unique();
            $table->text('value');
            $table->string('data_type', 30)->default('string'); // string, number, boolean, json
            $table->string('description', 255)->nullable();
            $table->boolean('is_public')->default(false);
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();

            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });

        // 4. Audit Logs (Immutable regulatory & operational audit trail)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('user_id')->nullable();
            $table->string('action', 100);
            $table->string('entity_type', 100);
            $table->string('entity_id', 100)->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['entity_type', 'entity_id']);
            $table->index(['user_id', 'created_at']);
        });

        // Database Trigger: Audit logs are strictly append-only (immutable)
        DB::statement("
            CREATE OR REPLACE FUNCTION fn_protect_audit_logs()
            RETURNS TRIGGER AS \$\$
            BEGIN
                RAISE EXCEPTION 'Audit logs are strictly append-only and cannot be updated or deleted!';
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER trg_protect_audit_logs
            BEFORE UPDATE OR DELETE ON audit_logs
            FOR EACH ROW
            EXECUTE FUNCTION fn_protect_audit_logs();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP TRIGGER IF EXISTS trg_protect_audit_logs ON audit_logs;");
        DB::statement("DROP FUNCTION IF EXISTS fn_protect_audit_logs();");

        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('system_configurations');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
