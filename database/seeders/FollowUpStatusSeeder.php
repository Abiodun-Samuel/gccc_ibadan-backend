<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FollowUpStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [

            [
                'title' => 'Invited Again',
                'slug' => 'invited-again',
                'color' => 'warning',
                'description' => 'Visitor has been invited to another event/service.',
            ],
            [
                'title' => 'Second Timer',
                'slug' => 'second-timer',
                'color' => 'dark',
                'description' => 'Visitor has attended for the second time.',
            ],
            [
                'title' => 'Third Timer',
                'slug' => 'third-timer',
                'color' => 'dark',
                'description' => 'Visitor has attended for the third time.',
            ],
            [
                'title' => 'Fourth Timer',
                'slug' => 'fourth-timer',
                'color' => 'dark',
                'description' => 'Visitor has attended for the fourth time or more.',
            ],
            [
                'title' => 'Contacted',
                'slug' => 'contacted',
                'color' => 'info',
                'description' => 'Visitor has been contacted at least once.',
            ],
            [
                'title' => 'Not Contacted',
                'slug' => 'not-contacted',
                'color' => 'light',
                'description' => 'Visitor not yet contacted for follow-up.',
            ],
            [
                'title' => 'Integrated',
                'slug' => 'integrated',
                'color' => 'success',
                'description' => 'Visitor is now integrated into the church/community.',
            ],
            [
                'title' => 'Visiting',
                'slug' => 'visiting',
                'color' => 'primary',
                'description' => 'Visitor is presently visiting (short-term).',
            ],
            [
                'title' => 'Opt-out',
                'slug' => 'opt-out',
                'color' => 'error',
                'description' => 'Visitor has opted out of follow-ups.',
            ],
        ];

        $now = Carbon::now();

        foreach ($statuses as $s) {
            DB::table('follow_up_statuses')->updateOrInsert(
                ['slug' => $s['slug']],
                array_merge($s, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }
}
