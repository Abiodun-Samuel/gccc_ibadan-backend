<?php

use App\Enums\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->foreignId('followup_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('follow_up_status_id')
                ->nullable()
                ->constrained('follow_up_statuses')
                ->nullOnDelete();

            $table->string('first_name')->index();
            $table->string('last_name')->index();
            $table->string('email')->unique();
            $table->string('avatar')->nullable();
            $table->string('secondary_avatar')->nullable();
            $table->string('phone_number');
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->enum('status', Status::values())->index()->default(Status::ACTIVE->value);
            $table->boolean('located_in_ibadan')->nullable()->default(false);
            $table->enum('membership_interest', ['Yes', 'No', 'Maybe'])->nullable();
            $table->string('born_again')->nullable();
            $table->boolean('whatsapp_interest')->nullable()->default(false);
            $table->boolean('is_student')->nullable()->default(false);
            $table->string('address')->nullable();
            $table->string('how_did_you_learn')->nullable();
            $table->string('invited_by')->nullable();
            $table->text('service_experience')->nullable();
            $table->text('prayer_point')->nullable();
            $table->longText('notes')->nullable();

            $table->string('community')->nullable()->index();
            $table->string('country')->nullable();
            $table->string('city_or_state')->nullable();
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('twitter')->nullable();
            $table->string('education')->nullable();
            $table->string('field_of_study')->nullable();
            $table->string('occupation')->nullable();
            $table->longText('visitation_report')->nullable();
            $table->longText('pastorate_call')->nullable();


            $table->unsignedInteger('attendance_badge')->default(0)->nullable();
            $table->unsignedTinyInteger('last_badge_month')->nullable();
            $table->unsignedSmallInteger('last_badge_year')->nullable();

            $table->date('week_ending')->nullable()->index();
            $table->timestamp('assigned_at')->nullable();
            $table->date('date_of_birth')->nullable()->index();
            $table->date('date_of_visit')->nullable()->index();
            $table->timestamp('email_verified_at')->nullable();

            $table->string('password');
            $table->rememberToken();
            $table->timestamps();

            $table->index(['last_badge_month', 'last_badge_year']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
