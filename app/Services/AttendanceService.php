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

        return Attendance::with('user:id,first_name,last_name,email,phone_number')
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
        return Attendance::updateOrCreate(
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
    public function getUserMonthlyStreak(User $user, Carbon $month): array
    {
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();

        // 1. Get all services
        $services = Service::all();

        // 2. Generate service occurrences for the month
        $occurrences = collect();

        foreach ($services as $service) {
            if ($service->is_recurring) {
                // recurring: generate all days matching `day_of_week`
                $dayName = strtolower($service->day_of_week);
                $date = $startOfMonth->copy()->next($dayName); // first occurrence in month

                while ($date->lte($endOfMonth)) {
                    $occurrences->push([
                        'service_id' => $service->id,
                        'date' => $date->copy(),
                    ]);
                    $date->addWeek();
                }
            } else {
                // custom: add if within month
                if (
                    $service->service_date &&
                    Carbon::parse($service->service_date)->between($startOfMonth, $endOfMonth)
                ) {
                    $occurrences->push([
                        'service_id' => $service->id,
                        'date' => Carbon::parse($service->service_date),
                    ]);
                }
            }
        }

        // Sort by date
        $occurrences = $occurrences->sortBy('date')->values();

        if ($occurrences->isEmpty()) {
            return [
                'longest_streak' => 0,
                'current_streak' => 0,
            ];
        }

        // 3. Get user attendance for these services
        $attendance = Attendance::query()
            ->where('user_id', $user->id)
            ->whereBetween('attendance_date', [$startOfMonth, $endOfMonth])
            ->where('status', 'present')
            ->get()
            ->mapWithKeys(fn($att) => [
                $att->service_id . '_' . Carbon::parse($att->attendance_date)->toDateString() => true
            ]);

        // 4. Walk through occurrences and calculate streaks
        $longestStreak = 0;
        $currentStreak = 0;

        foreach ($occurrences as $occurrence) {
            $key = $occurrence['service_id'] . '_' . $occurrence['date']->toDateString();

            if ($attendance->has($key)) {
                $currentStreak++;
                $longestStreak = max($longestStreak, $currentStreak);
            } else {
                $currentStreak = 0;
            }
        }

        return [
            'longest_streak' => $longestStreak,
            'current_streak' => $currentStreak,
        ];
    }
}
