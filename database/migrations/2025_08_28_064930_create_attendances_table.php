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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('attendance_date')->nullable()->index(); // actual service day
            $table->enum('status', ['present', 'absent'])->default('absent')->index();
            $table->enum('mode', ['onsite', 'online'])->nullable();
            $table->unique(['user_id', 'service_id', 'attendance_date'], 'attendance_unique');
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            Schema::dropIfExists('attendances');
        });
    }
};
