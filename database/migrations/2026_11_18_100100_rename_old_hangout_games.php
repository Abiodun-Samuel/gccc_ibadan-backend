<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registrations made with the first version of the hangout page stored some
 * games under names that have since changed. Without this, the admin view
 * lists both names (e.g. "Paint & Sip" beside "Sip & Paint") as if they were
 * different games, and the old one has no price.
 */
return new class extends Migration
{
    public const RENAMED = [
        'Paint & Sip'         => 'Sip & Paint',
        'Swimming'            => 'Swimming & Water Slides',
        'Trampoline'          => 'Trampoline & Bouncing Castle',
        'Basketball Shootout' => 'Basketball',
    ];

    public function up(): void
    {
        foreach (self::RENAMED as $old => $current) {
            DB::table('hangout_registrations')->where('game', $old)->update(['game' => $current]);
        }
    }

    public function down(): void
    {
        // The old names aren't games any more, so there is nothing to undo.
    }
};
