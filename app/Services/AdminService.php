<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\User;
use App\Models\FirstTimer;
use App\Models\Attendance;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AdminService
{
    public const CACHE_TTL = 180; // 5 minutes
    public const CACHE_PREFIX = 'Admin_analytics';

    /**
     * Get Admin Analytics (cached)
     */
    public function getAdminAnalytics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $cacheKey = $this->buildCacheKey($startDate, $endDate);

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn() => $this->calculateAdminAnalytics($startDate, $endDate)
        );
    }

    /**
     * Calculate Admin Analytics (no cache).
     */
    private function calculateAdminAnalytics(?Carbon $startDate, ?Carbon $endDate): array
    {
        $basicAnalytics = $this->getBasicAnalytics();
        $growthAnalytics = $this->getGrowthAnalytics($startDate, $endDate);

        return [
            [
                'name' => 'Members',
                'count' => $basicAnalytics['members'],
                'growth' => $growthAnalytics['member_growth'],
            ],
            [
                'name' => 'First timers',
                'count' => $basicAnalytics['firsttimers'],
                'growth' => $growthAnalytics['first_timer_growth'],
            ],
            [
                'name' => 'Attendance',
                'count' => $basicAnalytics['attendance'],
                'growth' => $growthAnalytics['attendance_growth'],
            ],
            [
                'name' => 'Units',
                'count' => $basicAnalytics['units'],
                'growth' => 0.00,
            ],
        ];
    }

    /**
     * Get basic counts in a single query per table.
     */
    private function getBasicAnalytics(): array
    {
        return [
            'firsttimers' => FirstTimer::count(),
            'members' => User::count(),
            'attendance' => Attendance::count(),
            'units' => Unit::count(),
        ];
    }

    /**
     * Calculate growth statistics for users, first timers, and attendance.
     */
    private function getGrowthAnalytics(Carbon $startDate, Carbon $endDate): array
    {

        $periodDays = $startDate->diffInDays($endDate);

        // Previous period is same length before current start date
        $prevStartDate = $startDate->copy()->subDays($periodDays);
        $prevEndDate = $startDate->copy()->subDay();

        // Aggregate queries to avoid duplicate round trips
        $currentMetrics = [
            'members' => User::inPeriod($startDate, $endDate)->count(),
            'first_timers' => FirstTimer::inPeriod($startDate, $endDate)->count(),
            'attendance' => Attendance::inPeriod($startDate, $endDate)->count(),
        ];

        $previousMetrics = [
            'members' => User::inPeriod($prevStartDate, $prevEndDate)->count(),
            'first_timers' => FirstTimer::inPeriod($prevStartDate, $prevEndDate)->count(),
            'attendance' => Attendance::inPeriod($prevStartDate, $prevEndDate)->count(),
        ];

        return [
            'member_growth' => $this->calculateGrowthRate($previousMetrics['members'], $currentMetrics['members']),
            'first_timer_growth' => $this->calculateGrowthRate($previousMetrics['first_timers'], $currentMetrics['first_timers']),
            'attendance_growth' => $this->calculateGrowthRate($previousMetrics['attendance'], $currentMetrics['attendance']),
        ];
    }

    /**
     * Calculate growth percentage.
     */
    private function calculateGrowthRate(int $previous, int $current): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }
        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Generate unique cache key.
     */
    private function buildCacheKey(?Carbon $startDate, ?Carbon $endDate): string
    {
        $start = $startDate?->format('Y-m-d') ?? 'default';
        $end = $endDate?->format('Y-m-d') ?? 'default';
        return self::CACHE_PREFIX . ":{$start}:{$end}";
    }

    /**
     * Clear cache.
     */
    public function clearCache(): void
    {
        Cache::flush();
    }
}
