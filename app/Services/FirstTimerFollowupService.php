<?php

namespace App\Services;

use App\Enums\UnitEnum;
use App\Models\FirstTimer;
use App\Models\User;
use App\Models\Unit;

class FirstTimerFollowupService
{
    public function findLeastLoadedFollowupMember(?string $gender = null): ?User
    {
        $followupUnitId = Unit::where('name', UnitEnum::FOLLOW_UP->value)->value('id');

        if (!$followupUnitId) {
            return null;
        }
        // return User::with(['units', 'assignedFirstTimers'])->query()
        return User::query()
            ->whereHas('units', fn($q) => $q->where('units.id', $followupUnitId))
            ->when($gender, fn($q) => $q->where('gender', $gender))
            ->withCount('assignedFirstTimers')
            ->orderBy('assigned_first_timers_count')
            ->orderBy('id')
            ->first();
    }


    /**
     * Unassign a first timer (optional).
     */
    public function unassign(FirstTimer $firstTimer): void
    {
        $firstTimer->assigned_to_member_id = null;
        $firstTimer->save();
    }
}
