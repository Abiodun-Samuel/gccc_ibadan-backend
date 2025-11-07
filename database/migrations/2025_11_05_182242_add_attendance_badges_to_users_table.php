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
            if (Schema::hasColumn('users', 'attendance_badge')) {
                $table->dropColumn(['attendance_badge', 'last_badge_month', 'last_badge_year']);
            }
            $table->json('attendance_badges')->nullable()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('attendance_badges');

            $table->unsignedInteger('attendance_badge')->default(0)->nullable();
            $table->unsignedTinyInteger('last_badge_month')->nullable();
            $table->unsignedSmallInteger('last_badge_year')->nullable();
        });
    }
};
