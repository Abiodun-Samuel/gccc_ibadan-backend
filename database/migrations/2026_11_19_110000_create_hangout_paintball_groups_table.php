<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paintball groups, kept so a player's group doesn't change: each adult or
 * child who picks paintball is given a group once, and keeps it until they
 * switch to another game or an admin moves them.
 *
 * Players who picked paintball before groups were kept get theirs the first
 * time the groups are read (App\Services\PaintballGroupService), arranged
 * the same way the page showed them until now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hangout_paintball_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('reference', 60)->nullable(); // the Bible verse the name comes from
            $table->string('motto', 160)->nullable();
            $table->timestamps();
        });

        foreach (['hangout_registrations', 'hangout_registration_children'] as $players) {
            Schema::table($players, function (Blueprint $table) {
                $table->foreignId('paintball_group_id')->nullable()->after('game')
                    ->constrained('hangout_paintball_groups')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['hangout_registrations', 'hangout_registration_children'] as $players) {
            Schema::table($players, function (Blueprint $table) {
                $table->dropConstrainedForeignId('paintball_group_id');
            });
        }

        Schema::dropIfExists('hangout_paintball_groups');
    }
};
