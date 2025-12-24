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
        Schema::create('picnic_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->year('year');
            $table->json('games');
            $table->decimal('support_amount', 10, 2)->nullable();
            $table->timestamp('registered_at');
            $table->timestamps();

            $table->unique(['user_id', 'year']);
            $table->index(['year', 'registered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('picnic_registrations');
    }
};
