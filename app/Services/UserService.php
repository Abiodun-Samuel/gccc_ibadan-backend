<?php

namespace App\Services;

use App\Models\AbsenteeAssignment;
use App\Models\FirstTimer;

class UserService
{
    public function getAssignedAbsenteesForLeader(int $leaderId)
    {
        return AbsenteeAssignment::with(['user', 'attendance.service'])
            ->where('leader_id', $leaderId)
            ->get();
    }
}
