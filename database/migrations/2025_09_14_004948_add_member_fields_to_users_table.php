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
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gender', 'date_of_visit', 'unit']);
            $table->string('country')->nullable()->after('status');
            $table->string('avatar')->nullable()->after('status');
            $table->string('city_or_state')->nullable()->after('country');
            $table->string('facebook')->nullable()->after('city_or_state');
            $table->string('instagram')->nullable()->after('facebook');
            $table->string('linkedin')->nullable()->after('instagram');
            $table->string('twitter')->nullable()->after('linkedin');
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable()->after('phone_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'country',
                'avatar',
                'city_or_state',
                'facebook',
                'instagram',
                'linkedin',
                'twitter'
            ]);
        });
    }
};
