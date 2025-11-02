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

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // Person being followed up
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete(); // The staff who followed up

            $table->enum('type', [
                'Pre-Service',
                'Post-Service',
                'Admin',
                'Pastor',
                'Unit-Leader',
                'Others'
            ])->nullable();
            $table->longText('note');
            $table->date('service_date')->index()->nullable();

            $table->timestamps();
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
