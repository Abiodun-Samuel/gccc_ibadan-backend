<?php

namespace App\Services;

use App\Models\UsherAttendance;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UsherAttendanceService
{
    private const CACHE_KEY = 'usher_attendance_list';
    private const CACHE_TTL = 3600;

    public function getAllAttendances(): Collection
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn() => UsherAttendance::
                orderBy('service_date', 'desc')
                ->get()
        );
    }

    public function store(array $data): UsherAttendance
    {
        $attendance = UsherAttendance::create($data);
        $this->clearCache();
        return $attendance;
    }

    public function update(UsherAttendance $attendance, array $data): UsherAttendance
    {
        $attendance->update($data);
        $this->clearCache();
        return $attendance;
    }

    public function getMonthlyAttendanceAnalytics(int $year): array
    {
        return Cache::remember("attendance_analytics_{$year}", self::CACHE_TTL, fn() => DB::table('usher_attendances')
            ->selectRaw('
                    service_day,
                    MONTH(service_date) as month,
                    AVG(total_attendance) as average_attendance,
                    SUM(total_attendance) as total_attendance,
                    COUNT(*) as services_count
                ')
            ->whereYear('service_date', $year)
            ->groupBy('service_day', DB::raw('MONTH(service_date)'))
            ->orderBy('month')
            ->orderBy('service_day')
            ->get());
    }

    public function delete(UsherAttendance $attendance): bool
    {
        $deleted = $attendance->delete();
        $this->clearCache();
        return $deleted;
    }

    private function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
