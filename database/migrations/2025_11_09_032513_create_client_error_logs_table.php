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
        Schema::create('client_error_logs', function (Blueprint $table) {
            $table->id();
            $table->longText('message')->nullable();
            $table->longText('stack')->nullable();
            $table->longText('component_stack')->nullable();
            $table->string('error_id')->nullable();
            $table->longText('url')->nullable();
            $table->longText('user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_error_logs');
    }
};
