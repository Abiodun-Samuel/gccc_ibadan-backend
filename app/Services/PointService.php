<?php

namespace App\Services;

use App\Config\PointRewards;
use App\Models\User;

/**
 * PointService
 * ─────────────────────────────────────────────────────────────
 *
 * Usage:
 *   $points = $this->pointService->award($user, PointRewards::ATTENDANCE_MARKED);
 */
class PointService
{

    private array $awarded = [];

    /*--------------------------------------------------------------
    | Award points for a named action
    --------------------------------------------------------------*/

    public function award(User $user, string $action): int
    {
        $points = PointRewards::get($action);

        if ($points === 0) return 0;

        // Prevent double-awarding the same action in one request
        $key = "{$user->id}:{$action}";
        if (isset($this->awarded[$key])) return 0;

        // Atomic SQL increment — safe against concurrent requests
        User::where('id', $user->id)->increment('reward_points', $points);

        $this->awarded[$key] = true;

        return $points;
    }
}
