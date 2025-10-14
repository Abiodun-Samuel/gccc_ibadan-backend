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
        Schema::create('first_timer_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('first_timer_id')->constrained('first_timers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['Pre-Service', 'Post-Service', 'Admin', 'Pastor', 'Unit-Leader', 'Others'])->nullable();
            $table->longText('note');
            $table->date('service_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('first_timer_follow_ups');
    }
};
