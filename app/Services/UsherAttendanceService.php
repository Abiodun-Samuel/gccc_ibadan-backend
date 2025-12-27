<?php

namespace App\Services;

use App\Models\UsherAttendance;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class UsherAttendanceService
{
    /**
     * Get all usher attendances
     *
     * @return Collection
     */
    public function getAllAttendances(): Collection
    {
        return UsherAttendance::orderBy('service_date', 'desc')->get();
    }

    /**
     * Create a new usher attendance record
     *
     * @param array $data
     * @return UsherAttendance
     */
    public function store(array $data): UsherAttendance
    {
        return UsherAttendance::create($data);
    }

    /**
     * Update an existing usher attendance record
     *
     * @param UsherAttendance $attendance
     * @param array $data
     * @return UsherAttendance
     */
    public function update(UsherAttendance $attendance, array $data): UsherAttendance
    {
        $attendance->update($data);
        return $attendance;
    }

    /**
     * Get monthly attendance analytics for a specific year
     *
     * @param int $year
     * @return SupportCollection
     */
    public function getMonthlyAttendanceAnalytics(int $year): SupportCollection
    {
        return DB::table('usher_attendances')
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
            ->get();
    }

    /**
     * Delete an usher attendance record
     *
     * @param UsherAttendance $attendance
     * @return bool
     */
    public function delete(UsherAttendance $attendance): bool
    {
        return $attendance->delete();
    }
}
