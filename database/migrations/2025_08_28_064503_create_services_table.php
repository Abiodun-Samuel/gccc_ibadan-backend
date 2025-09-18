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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. Sunday Service
            $table->longText('description')->nullable(); // e.g. Sunday Service
            $table->string('day_of_week')->nullable(); // e.g. sunday, tuesday, friday
            $table->time('start_time');
            $table->boolean('is_recurring')->default(true);
            $table->date('service_date')->nullable(); // for custom services
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            Schema::dropIfExists('services');
        });
    }
};
