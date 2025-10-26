<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\Form;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AdminService
{
    public const CACHE_TTL = 180; // 5 minutes
    public const CACHE_PREFIX = 'admin_analytics';

    public function getAdminAnalytics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $cacheKey = $this->buildCacheKey($startDate, $endDate);

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn() => $this->calculateAdminAnalytics($startDate, $endDate)
        );
    }

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

    private function getBasicAnalytics(): array
    {
        return [
            'firsttimers' => User::firstTimers()->count(),
            'members' => User::members()->count(),
            'forms' => Form::count(),
            'units' => Unit::count(),
        ];
    }

    private function buildCacheKey(?Carbon $startDate, ?Carbon $endDate): string
    {
        $start = $startDate?->format('Y-m-d') ?? 'default';
        $end = $endDate?->format('Y-m-d') ?? 'default';
        return self::CACHE_PREFIX . ":{$start}:{$end}";
    }

    public function clearCache(): void
    {
        Cache::flush();
    }
}
