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


    public function getChartData(?int $year = null): array
    {
        $year = $year ?? now()->year;

        $rows = DB::table('usher_attendances')
            ->selectRaw('
            service_day,
            MAX(service_day_desc) as service_day_desc,
            MONTH(service_date)   as month,
            SUM(total_attendance) as total_attendance,
            ROUND(AVG(total_attendance), 2) as average_attendance,
            COUNT(*)              as services_count
        ')
            ->whereYear('service_date', $year)
            ->groupBy('service_day', DB::raw('MONTH(service_date)'))
            ->orderBy('service_day')
            ->orderBy('month')
            ->get();

        $series = [];

        foreach ($rows as $row) {
            if (!isset($series[$row->service_day])) {
                $series[$row->service_day] = [
                    'service_day'      => $row->service_day,
                    'service_day_desc' => $row->service_day_desc,
                    'monthly_data'     => array_fill(1, 12, [
                        'total_attendance'   => 0,
                        'average_attendance' => 0,
                        'services_count'     => 0,
                    ]),
                ];
            }

            $series[$row->service_day]['monthly_data'][(int) $row->month] = [
                'total_attendance'   => (int) $row->total_attendance,
                'average_attendance' => (float) $row->average_attendance,
                'services_count'     => (int) $row->services_count,
            ];
        }

        $months = [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Aug',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dec',
        ];

        $chart = [];

        foreach ($series as $entry) {
            $monthly = [];

            foreach ($months as $num => $label) {
                $monthly[] = [
                    'month'              => $label,
                    'month_number'       => $num,
                    'total_attendance'   => $entry['monthly_data'][$num]['total_attendance'],
                    'average_attendance' => $entry['monthly_data'][$num]['average_attendance'],
                    'services_count'     => $entry['monthly_data'][$num]['services_count'],
                ];
            }

            $chart[] = [
                'service_day'      => $entry['service_day'],
                'service_day_desc' => $entry['service_day_desc'],
                'monthly_data'     => $monthly,
            ];
        }

        return [
            'year'   => $year,
            'series' => $chart,
        ];
    }
}
