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
            $table->string('name')->index();
            $table->longText('description')->nullable();
            $table->string('day_of_week')->index()->nullable();
            $table->time('start_time')->index()->nullable();
            $table->boolean('is_recurring')->default(true);
            $table->date('service_date')->index()->nullable();
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
