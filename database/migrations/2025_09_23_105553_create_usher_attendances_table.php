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
        Schema::create('usher_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('male')->index();
            $table->unsignedInteger('female')->index();
            $table->unsignedInteger('children')->index();
            $table->unsignedInteger('total_attendance')->index();
            $table->date('service_date')->index();
            $table->string('service_day')->index();
            $table->string('service_day_desc')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usher_attendances');
    }
};
