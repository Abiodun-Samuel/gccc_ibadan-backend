<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('followup_feedbacks', function (Blueprint $table) {
            $table->id();
            // Polymorphic relationship - can be attached to FirstTimer or User
            $table->morphs('followupable'); // Creates followupable_type and followupable_id

            // User who created the feedback (the follower-upper)
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Feedback details
            $table->enum('type', [
                'Pre-Service',
                'Post-Service',
                'Admin',
                'Pastor',
                'Unit-Leader',
                'Others'
            ])->nullable();
            $table->longText('note');
            $table->date('service_date')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index('service_date');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('followup_feedbacks');
    }
};
