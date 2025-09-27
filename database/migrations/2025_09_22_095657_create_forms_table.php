<?php

use App\Enums\FormTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->enum('type', FormTypeEnum::values())->index();
            $table->string('name')->nullable()->index();
            $table->boolean('isCompleted')->nullable()->default(false)->index();
            $table->string('phone_number', 20)->nullable()->index();
            $table->boolean('wants_to_share_testimony')->nullable();
            $table->longText('content');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
