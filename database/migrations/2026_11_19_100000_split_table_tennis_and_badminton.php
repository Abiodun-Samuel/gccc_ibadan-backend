<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Table Tennis & Badminton" was one game for a short while; they're now two
 * (Table Tennis ₦500, Badminton ₦1,000). Anyone still saved under the joint
 * name moves to Table Tennis, the first of the two, and can switch to
 * Badminton by updating their registration. Without this the admin view
 * would list the old joint name as a game of its own, with no price.
 */
return new class extends Migration
{
    public const OLD = 'Table Tennis & Badminton';
    public const CURRENT = 'Table Tennis';

    public function up(): void
    {
        DB::table('hangout_registrations')->where('game', self::OLD)->update(['game' => self::CURRENT]);
        DB::table('hangout_registration_children')->where('game', self::OLD)->update(['game' => self::CURRENT]);
    }

    public function down(): void
    {
        // The joint name isn't a game any more, so there is nothing to undo.
    }
};
