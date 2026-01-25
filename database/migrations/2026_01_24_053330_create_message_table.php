<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sender_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('recipient_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('subject')->nullable();
            $table->longText('body');

            $table->timestamp('read_at')->nullable()->index();

            $table->boolean('archived_by_sender')->default(false)->index();
            $table->boolean('archived_by_recipient')->default(false)->index();
            $table->boolean('deleted_by_sender')->default(false)->index();
            $table->boolean('deleted_by_recipient')->default(false)->index();

            $table->timestamps();

            // Composite indexes for efficient queries
            $table->index(['recipient_id', 'deleted_by_recipient', 'created_at'], 'idx_inbox_messages');
            $table->index(['sender_id', 'deleted_by_sender', 'created_at'], 'idx_sent_messages');
            $table->index(['recipient_id', 'read_at', 'deleted_by_recipient'], 'idx_unread_messages');
            $table->index(['sender_id', 'recipient_id', 'created_at'], 'idx_conversation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
