<?php

namespace App\Services;

use App\Enums\UnitEnum;
use App\Models\User;
use App\Models\Unit;

class FirstTimerService
{
    public function findLeastLoadedFollowupMember(?string $gender = null): ?User
    {
        $followupUnitId = Unit::where('name', UnitEnum::FOLLOW_UP->value)->value('id');
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
}
