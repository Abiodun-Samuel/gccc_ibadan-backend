<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The meal each member picks. Nullable because registrations made before
 * meals were added have none until the member updates their registration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hangout_registrations', function (Blueprint $table) {
            $table->string('menu', 100)->nullable()->after('game')->index();
        });
    }

    public function down(): void
    {
        Schema::table('hangout_registrations', function (Blueprint $table) {
            $table->dropIndex(['menu']);
            $table->dropColumn('menu');
        });
    }
};
