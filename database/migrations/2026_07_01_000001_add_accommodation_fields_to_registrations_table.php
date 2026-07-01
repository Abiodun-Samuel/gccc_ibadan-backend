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
        Schema::table('registrations', function (Blueprint $table) {
            $table->boolean('needs_accommodation')->nullable()->after('attending');
            $table->boolean('travelling_with_us')->nullable()->after('needs_accommodation');
            $table->boolean('needs_transport_fare_help')->nullable()->after('travelling_with_us');
            $table->date('travel_date')->nullable()->after('needs_transport_fare_help');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn([
                'needs_accommodation',
                'travelling_with_us',
                'needs_transport_fare_help',
                'travel_date',
            ]);
        });
    }
};
