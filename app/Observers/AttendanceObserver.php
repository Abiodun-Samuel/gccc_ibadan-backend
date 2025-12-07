<?php

namespace App\Observers;

use App\Enums\RoleEnum;
use App\Models\Attendance;
use App\Models\FollowUpStatus;
use Illuminate\Support\Facades\DB;

class AttendanceObserver
{
    private function upgradeToMember($user): void
    {
        try {
            DB::beginTransaction();
            $user->update(['follow_up_status_id' => FollowUpStatus::INTEGRATED_ID]);
            $user->assignRole(RoleEnum::MEMBER->value);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
        }
    }
    private function checkAndUpgradeToMember($user): void
    {
        $sundayCount = $user->attendances()
            ->present()
            ->whereHas(
                'service',
                fn($q) =>
                $q->where('day_of_week', 'sunday')
            )
            ->count();

        if ($sundayCount >= 4) {
            $this->upgradeToMember($user);
        }
    }

    public function created(Attendance $attendance): void
    {
        if ($attendance->status !== 'present') {
            return;
        }
        $user = $attendance->user;

        if ($user->hasRole(RoleEnum::FIRST_TIMER->value) && !$user->hasRole(RoleEnum::MEMBER->value)) {
            $this->checkAndUpgradeToMember($user);
        }
    }
}
