<?php

use App\Enums\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('first_timers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('first_name')->index();
            $table->string('last_name')->index();
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable()->index();
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->boolean('located_in_ibadan')->nullable()->default(false);
            $table->enum('interest', ['Yes', 'No', 'Maybe'])->nullable();
            $table->enum('status', Status::values())->index()->default(Status::ACTIVE->value);
            $table->enum('born_again', ['Yes', 'No', 'Uncertain'])->nullable();
            $table->boolean('whatsapp_interest')->nullable()->default(true);
            $table->boolean('is_student')->nullable()->default(false);
            $table->string('address')->nullable();
            $table->date('date_of_visit')->nullable()->index();
            $table->date('date_of_birth')->nullable();
            $table->string('occupation')->nullable();
            $table->string('how_did_you_learn')->nullable();
            $table->string('friend_family')->nullable();
            $table->string('invited_by')->nullable();
            $table->text('service_experience')->nullable();
            $table->text('prayer_point')->nullable();
            $table->text('notes')->nullable();
            $table->date('week_ending')->nullable();
            $table->text('visitation_report')->nullable();
            $table->text('pastorate_call')->nullable();
            $table->dateTime('assigned_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('first_timers');
    }
};
