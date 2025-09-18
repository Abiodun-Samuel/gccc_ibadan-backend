<?php

namespace App\Services;

use App\Models\FirstTimer;
use App\Models\User;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;

class FirstTimerFollowupService
{
    public function findLeastLoadedFollowupMember(?string $gender = null): ?User
    {
        $followupUnitId = Unit::where('name', 'Follow-up')->value('id');

        if (!$followupUnitId) {
            return null;
        }

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
