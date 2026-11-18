<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each member's gender. Required when registering or updating; nullable only
 * because registrations made before it was asked have none until the member
 * updates their registration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hangout_registrations', function (Blueprint $table) {
            $table->string('gender', 10)->nullable()->after('full_name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('hangout_registrations', function (Blueprint $table) {
            $table->dropIndex(['gender']);
            $table->dropColumn('gender');
        });
    }
};
