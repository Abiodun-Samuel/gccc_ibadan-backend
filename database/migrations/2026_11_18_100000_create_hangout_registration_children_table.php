<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Children an adult brings to the hangout, added to the adult's own
 * registration. They need no email or phone of their own: the adult's
 * contact details cover them. Each picks a game and a meal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hangout_registration_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hangout_registration_id')->constrained('hangout_registrations')->cascadeOnDelete();
            $table->string('full_name', 150);
            $table->string('gender', 10)->index();
            $table->string('game', 100)->index();
            $table->string('menu', 100)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hangout_registration_children');
    }
};
