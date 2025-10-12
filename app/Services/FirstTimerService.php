<?php

namespace App\Services;

use App\Enums\UnitEnum;
use App\Models\FirstTimer;
use App\Models\User;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    public function updateFirstTimer(FirstTimer $firstTimer, array $data): FirstTimer
    {
        return DB::transaction(function () use ($firstTimer, $data) {
            $firstTimer->update($data);
            $firstTimer->load(['followUpStatus', 'assignedTo']);
            return $firstTimer->fresh(['followUpStatus', 'assignedTo']);
        });
    }
    public function getFirstTimersAssigned(int $memberId): ?Collection
    {
        return FirstTimer::where('assigned_to_member_id', $memberId)->with(['followUpStatus', 'assignedTo'])->orderBy('date_of_visit', 'desc')->get();
    }
}
