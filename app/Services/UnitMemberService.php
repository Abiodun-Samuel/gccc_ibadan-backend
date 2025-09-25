<?php

namespace App\Services;

use App\Models\Unit;
use Illuminate\Support\Collection;

class UnitMemberService
{
    /**
     * Assign one or many users to a unit as members.
     */
    public function assignMembers(Unit $unit, array $userIds): void
    {
        // Sync without detaching so existing members remain
        $unit->members()->syncWithoutDetaching($userIds);
    }

    /**
     * Unassign one or many users from a unit.
     */
    public function unassignMembers(Unit $unit, array $userIds): void
    {
        $unit->members()->detach($userIds);
    }

    /**
     * Get all members of a unit.
     */
    public function getMembers(Unit $unit): Collection
    {
        return $unit->members;
    }
}
