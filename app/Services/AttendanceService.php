<?php

namespace App\Services;

use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AttendanceService
{
    private const TIMEZONE = 'Africa/Lagos';
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Get all attendance records with pagination and optimized queries
     */
    public function getAllAttendance(int $perPage = 15): LengthAwarePaginator
    {
        return Attendance::with([
            'user:id,first_name,last_name,email,phone_number',
            'service:id,name,start_time,day_of_week,service_date'
        ])
            ->select([
                'id',
                'user_id',
                'service_id',
                'attendance_date',
                'status',
                'mode',
                'created_at'
            ])
            ->latest('attendance_date')
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Mark attendance for a user with business logic validation
     */
    public function markUserAttendance(User $user, array $data): Attendance
    {
        $service = $this->getServiceWithCache($data['service_id']);
        $attendanceDate = $this->getServiceDate($service);

        // Optional: Validate service timing
        // $this->validateServiceTiming($service);

        return $this->upsertAttendance([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'attendance_date' => $attendanceDate,
            'status' => $data['status'],
            'mode' => $data['status'] === 'present' ? $data['mode'] : null,
        ]);
    }

    /**
     * Admin bulk mark attendance with transaction safety
     */
    public function adminMarkAttendance(array $data): void
    {
        $service = $this->getServiceWithCache($data['service_id']);
        $attendanceDate = Carbon::parse($data['attendance_date'], self::TIMEZONE)
            ->toDateString();

        DB::transaction(function () use ($data, $service, $attendanceDate) {
            // Prepare bulk upsert data
            $attendanceRecords = collect($data['attendances'])->map(fn($attendance) => [
                'user_id' => $attendance['user_id'],
                'service_id' => $service->id,
                'attendance_date' => $attendanceDate,
                'status' => $attendance['status'],
                'mode' => $attendance['status'] === 'present' ? ($attendance['mode'] ?? null) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Use efficient upsert for bulk operations
            $this->bulkUpsertAttendance($attendanceRecords->toArray());
        });

        // Clear relevant caches
        $this->clearAttendanceCache($data['service_id'], $attendanceDate);
    }

    /**
     * Get paginated attendance history for a user
     */
    public function getUserAttendanceHistory(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $user->attendances()
            ->with('service:id,name,start_time')
            ->select([
                'id',
                'service_id',
                'attendance_date',
                'status',
                'mode',
                'created_at'
            ])
            ->latest('attendance_date')
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Mark all users without attendance record as absent
     */
    public function markAbsentees(array $data): int
    {
        $serviceId = $data['service_id'];
        $attendanceDate = Carbon::parse($data['date'], self::TIMEZONE)->toDateString();

        // Get users who already have attendance records for this service/date
        $markedUserIds = Attendance::where('service_id', $serviceId)
            ->whereDate('attendance_date', $attendanceDate)
            ->pluck('user_id');

        // Insert absent records for unmarked users
        $inserted = DB::table('attendances')->insertUsing(
            ['user_id', 'service_id', 'attendance_date', 'status', 'mode', 'created_at', 'updated_at'],
            DB::table('users')
                ->whereNotIn('id', $markedUserIds)
                ->where('is_active', true) // Assuming you have an active status
                ->select([
                    'id as user_id',
                    DB::raw("{$serviceId} as service_id"),
                    DB::raw("'{$attendanceDate}' as attendance_date"),
                    DB::raw("'absent' as status"),
                    DB::raw("NULL as mode"),
                    DB::raw("NOW() as created_at"),
                    DB::raw("NOW() as updated_at")
                ])
        );

        $this->clearAttendanceCache($serviceId, $attendanceDate);

        return $inserted;
    }

    /**
     * Get absentees for a specific service and date
     */
    public function getAbsentees(array $data): Collection
    {
        $serviceId = $data['service_id'];
        $attendanceDate = Carbon::parse($data['date'], self::TIMEZONE)->toDateString();

        return Attendance::with('user:id,first_name,last_name,email,phone_number', 'service')
            ->where('service_id', $serviceId)
            ->whereDate('attendance_date', $attendanceDate)
            ->where('status', 'absent')
            ->select(['id', 'user_id', 'service_id', 'attendance_date', 'status', 'created_at'])
            ->latest('created_at')
            ->get();
    }

    /**
     * Get service with caching for performance
     */
    private function getServiceWithCache(int $serviceId): Service
    {
        $cacheKey = "service:{$serviceId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($serviceId) {
            $service = Service::find($serviceId);

            if (!$service) {
                throw new AttendanceException("Service not found", 404);
            }

            return $service;
        });
    }

    /**
     * Get service date (today for recurring, specific date for one-time)
     */
    private function getServiceDate(Service $service): string
    {
        return $service->date ?? Carbon::now(self::TIMEZONE)->toDateString();
    }

    /**
     * Validate service timing (optional - currently commented out in original)
     */
    private function validateServiceTiming(Service $service): void
    {
        if (!$service->start_time) {
            return; // Skip validation if no start time set
        }

        $serviceTime = Carbon::parse($service->start_time, self::TIMEZONE);
        $now = Carbon::now(self::TIMEZONE);
        $validUntil = $serviceTime->copy()->addHours(4);

        if (!$now->between($serviceTime, $validUntil)) {
            throw new AttendanceException(
                "Attendance can only be marked within 4 hours of service time.",
                403
            );
        }
    }

    /**
     * Upsert single attendance record
     */
    private function upsertAttendance(array $data): Attendance
    {
        $attendance = Attendance::updateOrCreate(
            [
                'user_id' => $data['user_id'],
                'service_id' => $data['service_id'],
                'attendance_date' => $data['attendance_date'],
            ],
            [
                'status' => $data['status'],
                'mode' => $data['mode'],
            ]
        );
        return $attendance->load(['user', 'service']);
    }

    /**
     * Bulk upsert attendance records for better performance
     */
    private function bulkUpsertAttendance(array $records): void
    {
        foreach ($records as $record) {
            Attendance::updateOrCreate(
                [
                    'user_id' => $record['user_id'],
                    'service_id' => $record['service_id'],
                    'attendance_date' => $record['attendance_date'],
                ],
                [
                    'status' => $record['status'],
                    'mode' => $record['mode'],
                ]
            );
        }
        // Alternative for MySQL 8.0+:
        // DB::table('attendances')->upsert(
        //     $records,
        //     ['user_id', 'service_id', 'attendance_date'],
        //     ['status', 'mode', 'updated_at']
        // );
    }

    /**
     * Clear attendance-related cache
     */
    private function clearAttendanceCache(int $serviceId, string $date): void
    {
        $patterns = [
            "attendance:service:{$serviceId}:date:{$date}",
            "absentees:service:{$serviceId}:date:{$date}",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Get monthly attendance statistics (average or total).
     *
     * @param string $mode  avg|total
     * @param int|null $year optional year filter
     * @return array
     */
    public function getMonthlyStats(string $mode = 'avg', ?int $year = null): array
    {
        $aggregates = [
            'avg' => 'ROUND(AVG(male),2) as male, ROUND(AVG(female),2) as female, ROUND(AVG(children),2) as children',
            'total' => 'SUM(male) as male, SUM(female) as female, SUM(children) as children',
        ];

        if (!isset($aggregates[$mode])) {
            throw new \InvalidArgumentException('Invalid mode. Use avg or total.');
        }

        $query = DB::table('usher_attendances')
            ->selectRaw("
                YEAR(service_date) as year,
                MONTH(service_date) as month_num,
                DATE_FORMAT(MIN(service_date), '%b') as month_label,
                {$aggregates[$mode]}
            ");

        if ($year) {
            $query->whereYear('service_date', $year);
        }

        $results = $query
            ->groupBy(DB::raw("YEAR(service_date), MONTH(service_date)"))
            ->orderBy(DB::raw("YEAR(service_date), MONTH(service_date)"))
            ->get();

        $dataset = $results->map(function ($row) {
            return [
                'year' => (int) $row->year,
                'month' => $row->month_label,
                'monthNum' => (int) $row->month_num,
                'male' => (float) $row->male,
                'female' => (float) $row->female,
                'children' => (float) $row->children,
            ];
        });

        $series = [
            ['type' => 'bar', 'xKey' => 'month', 'yKey' => 'male', 'yName' => 'Male'],
            ['type' => 'bar', 'xKey' => 'month', 'yKey' => 'female', 'yName' => 'Female'],
            ['type' => 'bar', 'xKey' => 'month', 'yKey' => 'children', 'yName' => 'Children'],
        ];

        return [
            'dataset' => $dataset,
            'series' => $series,
            'mode' => $mode,
        ];
    }
}
