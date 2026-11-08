<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Registrations for the GCCC Hangout, kept apart from event registrations.
     * One row per email: registering again with the same email updates it.
     */
    public function up(): void
    {
        Schema::create('hangout_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 150);
            $table->string('email')->unique(); // stored lower-cased
            $table->string('phone_number', 25);
            $table->string('whatsapp_number', 25)->nullable();
            $table->string('game', 100)->index();
            $table->decimal('support_amount', 12, 2)->nullable(); // pledged support, in naira
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hangout_registrations');
    }
};
