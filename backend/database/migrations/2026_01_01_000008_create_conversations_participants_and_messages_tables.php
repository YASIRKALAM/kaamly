<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * In-app chat, real-time messaging, participants, and attachments.
     */
    public function up(): void
    {
        // 1. Conversations
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('booking_id')->nullable();
            $table->string('conversation_type', 30)->default('booking_chat'); // booking_chat, support_ticket, direct
            $table->string('status', 30)->default('active'); // active, closed, archived
            $table->timestampsTz();

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('set null');
            $table->index(['booking_id', 'status']);
        });

        // 2. Conversation Participants
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('conversation_id');
            $table->uuid('user_id');
            $table->string('role_in_conversation', 30)->default('participant'); // customer, provider, support_agent
            $table->timestampTz('last_read_at')->nullable();
            $table->timestampTz('joined_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('left_at')->nullable();

            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['conversation_id', 'user_id']);
        });

        // 3. Messages
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('conversation_id');
            $table->uuid('sender_id');
            $table->string('message_type', 30)->default('text'); // text, image, location, system_event
            $table->text('content');
            $table->boolean('is_read')->default(false);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['conversation_id', 'created_at']);
        });

        // 4. Message Attachments
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('message_id');
            $table->string('file_path_s3', 500);
            $table->string('file_mime_type', 100)->nullable();
            $table->bigInteger('file_size_bytes')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->foreign('message_id')->references('id')->on('messages')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
