<?php

/**
 * ==========================================
 * ATTENDANCE REWARDS SYSTEM
 * ==========================================
 *
 * Feature: Reward users with points for attending services
 * - Sunday services: 3 points (baseline)
 * - Mid-week services: 5 points (higher engagement reward)
 *
 * Suggested Name: "Faithfulness Points" or "Attendance Credits"
 */

// ==========================================
// STEP 1: DATABASE MIGRATION
// ==========================================
// File: database/migrations/2026_01_24_create_attendance_rewards_system.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedInteger('reward_stars')->default(5)->after('service_date');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('total_stars')->default(0)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('reward_stars');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('total_stars');
        });
    }
};
