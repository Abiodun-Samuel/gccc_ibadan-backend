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
        Schema::table('first_timers', function (Blueprint $table) {
            if (Schema::hasColumn('first_timers', 'follow_up_status')) {
                $table->dropColumn('follow_up_status');
            }
            if (Schema::hasColumn('first_timers', 'follow_up_by')) {
                $table->dropColumn('follow_up_by');
            }
            // new columns
            $table->foreignId('follow_up_status_id')
                ->after('id')
                ->nullable()
                ->constrained('follow_up_statuses')
                ->nullOnDelete();

            $table->foreignId('assigned_to_member_id')
                ->after('id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('first_timers', function (Blueprint $table) {
            $table->dropForeign(['follow_up_status_id']);
            $table->dropForeign(['assigned_member_id']);
            $table->dropIndex(['follow_up_status_id', 'assigned_member_id']);
            $table->dropColumn(['follow_up_status_id', 'assigned_member_id']);
        });
    }
};
