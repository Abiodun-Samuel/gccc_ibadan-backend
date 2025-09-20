<?php

namespace Database\Seeders;

use App\Enums\FollowUpStatusEnum;
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
                'title' => FollowUpStatusEnum::INVITED_AGAIN->value,
                'color' => 'warning',
                'description' => 'Visitor has been invited to another event/service.',
            ],
            [
                'title' => FollowUpStatusEnum::SECOND_TIMER->value,
                'color' => 'dark',
                'description' => 'Visitor has attended for the second time.',
            ],
            [
                'title' => FollowUpStatusEnum::THIRD_TIMER->value,
                'color' => 'dark',
                'description' => 'Visitor has attended for the third time.',
            ],
            [
                'title' => FollowUpStatusEnum::FOURTH_TIMER->value,
                'color' => 'dark',
                'description' => 'Visitor has attended for the fourth time or more.',
            ],
            [
                'title' => FollowUpStatusEnum::CONTACTED->value,
                'color' => 'info',
                'description' => 'Visitor has been contacted at least once.',
            ],
            [
                'title' => FollowUpStatusEnum::NOT_CONTACTED->value,
                'color' => 'light',
                'description' => 'Visitor not yet contacted for follow-up.',
            ],
            [
                'title' => FollowUpStatusEnum::INTEGRATED->value,
                'color' => 'success',
                'description' => 'Visitor is now integrated into the church/community.',
            ],
            [
                'title' => FollowUpStatusEnum::VISITING->value,
                'color' => 'primary',
                'description' => 'Visitor is presently visiting (short-term).',
            ],
            [
                'title' => FollowUpStatusEnum::OPT_OUT->value,
                'color' => 'error',
                'description' => 'Visitor has opted out of follow-ups.',
            ],
        ];

        $now = Carbon::now();

        foreach ($statuses as $s) {
            DB::table('follow_up_statuses')->updateOrInsert(
                ['title' => $s['title']],
                array_merge($s, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }
}
