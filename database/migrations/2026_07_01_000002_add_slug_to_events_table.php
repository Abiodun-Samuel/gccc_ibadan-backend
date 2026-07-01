<?php

use App\Models\Event;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('title');
        });

        // Backfill slugs for existing events.
        Event::withoutGlobalScopes()->whereNull('slug')->get()->each(function (Event $event) {
            $base = Str::slug($event->title) ?: 'event';
            $slug = $base;
            $suffix = 2;

            while (Event::withoutGlobalScopes()->where('slug', $slug)->where('id', '!=', $event->id)->exists()) {
                $slug = "{$base}-{$suffix}";
                $suffix++;
            }

            $event->slug = $slug;
            $event->saveQuietly();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
