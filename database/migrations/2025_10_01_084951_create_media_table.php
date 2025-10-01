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
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('video_id')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('thumbnail_default')->nullable();
            $table->string('thumbnail_medium')->nullable();
            $table->string('thumbnail_high')->nullable();
            $table->string('channel_id')->nullable();
            $table->string('channel_title')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
