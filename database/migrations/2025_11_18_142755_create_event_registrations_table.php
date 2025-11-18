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
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // Person being followed up

            $table->string('event');
            $table->json('selected_dates')->nullable();
            $table->integer('num_days')->nullable();
            $table->integer('nights')->default(1);

            $table->boolean('accommodation')->default(false);
            $table->boolean('feeding')->default(true);

            $table->decimal('feeding_cost', 10, 2)->nullable();
            $table->decimal('transport_cost', 10, 2)->nullable();
            $table->boolean('couples')->default(false);
            $table->decimal('couples_cost', 10, 2)->nullable();
            $table->decimal('total', 10, 2)->nullable();

            $table->boolean('interested_in_serving')->nullable();
            $table->boolean('integrated_into_a_unit')->nullable();
            $table->string('specify_unit')->nullable();
            $table->boolean('is_student')->default(false);
            $table->string('institution')->nullable();

            $table->json('transportation')->nullable(); // { to: bool, fro: bool }

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
