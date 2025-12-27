<?php

namespace App\Services;

use App\Models\Form;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;

class AdminService
{
    /**
     * Get admin analytics with optional date filtering.
     *
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return array
     */
    public function getAdminAnalytics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        return $this->calculateAdminAnalytics($startDate, $endDate);
    }

    /**
     * Calculate admin analytics data.
     *
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return array
     */
    private function calculateAdminAnalytics(?Carbon $startDate, ?Carbon $endDate): array
    {
        $basicAnalytics = $this->getBasicAnalytics();

        return [
            [
                'name' => 'Members',
                'count' => $basicAnalytics['members'],
            ],
            [
                'name' => 'First timers',
                'count' => $basicAnalytics['firsttimers'],
            ],
            [
                'name' => 'Forms',
                'count' => $basicAnalytics['forms'],
            ],
            [
                'name' => 'Units',
                'count' => $basicAnalytics['units'],
            ],
        ];
    }

    /**
     * Get basic analytics counts.
     *
     * @return array
     */
    private function getBasicAnalytics(): array
    {
        return [
            'firsttimers' => User::firstTimers()->count(),
            'members' => User::members()->count(),
            'forms' => Form::count(),
            'units' => Unit::count(),
        ];
    }
}
