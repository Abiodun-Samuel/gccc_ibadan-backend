<?php

namespace App\Services;

use App\Exceptions\AttendanceException;
use App\Models\AbsenteeAssignment;
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
    private const CACHE_TTL = 300;


    public function getAllAttendance(array $filters = []): Collection
    {
        $cacheKey = $this->generateCacheKey('attendance:all', $filters);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters) {
            $query = Attendance::query();

            $query->when(
                !empty($filters['service_id']),
                fn($q) => $q->where('service_id', $filters['service_id'])
            );

            $query->when(
                !empty($filters['status']),
                fn($q) => $q->where('status', $filters['status'])
            );

            $query->when(
                !empty($filters['mode']),
                fn($q) => $q->where('mode', $filters['mode'])
            );

            $query->when(!empty($filters['attendance_date']), function ($q) use ($filters) {
                $dates = is_array($filters['attendance_date'])
                    ? $filters['attendance_date']
                    : [$filters['attendance_date']];

                return $q->whereIn('attendance_date', $dates);
            });

            return $query->with([
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
                ->get();
        });
    }

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

    private function getServiceDate(Service $service): string
    {
        return $service->date ?? Carbon::now(self::TIMEZONE)->toDateString();
    }

    public function getUserAttendanceHistory(User $user): Collection
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
            ->get();
    }

    public function markAbsentees(array $data): int
    {
        $serviceId = $data['service_id'];
        $attendanceDate = Carbon::parse($data['date'], self::TIMEZONE)->toDateString();

        $markedUserIds = Attendance::where('service_id', $serviceId)
            ->whereDate('attendance_date', $attendanceDate)
            ->pluck('user_id');

        $inserted = DB::table('attendances')->insertUsing(
            ['user_id', 'service_id', 'attendance_date', 'status', 'mode', 'created_at', 'updated_at'],
            DB::table('users')
                ->whereNotIn('id', $markedUserIds)
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
        $this->clearAttendanceCache();
        return $inserted;
    }

    public function getAbsentees(array $data): Collection
    {
        $serviceId = (int) $data['service_id'];
        $attendanceDate = Carbon::parse($data['date'], self::TIMEZONE)->toDateString();

        $cacheKey = $this->generateCacheKey('absentees', [
            'service_id' => $serviceId,
            'date' => $attendanceDate,
        ]);

        return Cache::remember($cacheKey, self::CACHE_TTL, fn() => Attendance::with(['user:id,first_name,last_name,email,phone_number,gender'])
            ->where('service_id', $serviceId)
            ->whereDate('attendance_date', $attendanceDate)
            ->where('status', 'absent')
            ->select(['id', 'user_id', 'service_id', 'attendance_date', 'status', 'created_at'])
            ->get());
    }

    public function assignAbsenteesToLeaders(array $data): array
    {
        $leaderIds = $data['leader_ids'];
        $serviceId = (int) $data['service_id'];
        $attendanceDate = Carbon::parse($data['attendance_date'], self::TIMEZONE)->toDateString();

        // Validate leaders exist
        $validLeaderIds = User::whereIn('id', $leaderIds)->pluck('id')->toArray();

        if (count($validLeaderIds) !== count($leaderIds)) {
            throw new AttendanceException('One or more leader IDs are invalid');
        }

        if (empty($validLeaderIds)) {
            throw new AttendanceException('At least one valid leader is required');
        }

        // Validate service exists
        if (!Service::where('id', $serviceId)->exists()) {
            throw new AttendanceException('Service not found');
        }

        return DB::transaction(function () use ($validLeaderIds, $serviceId, $attendanceDate) {
            // Fetch absent member IDs with single optimized query
            $absentMemberIds = Attendance::where('service_id', $serviceId)
                ->whereDate('attendance_date', $attendanceDate)
                ->where('status', 'absent')
                ->pluck('user_id')
                ->toArray();

            if (empty($absentMemberIds)) {
                return [
                    'assigned_count' => 0,
                    'leaders_count' => count($validLeaderIds),
                    'distribution' => [],
                    'message' => 'No absent members to assign'
                ];
            }

            // Distribute members evenly using round-robin
            $assignments = $this->buildAssignments(
                $absentMemberIds,
                $validLeaderIds,
                $serviceId,
                $attendanceDate
            );

            // Bulk insert for maximum performance
            AbsenteeAssignment::insert($assignments);

            // Calculate distribution for response
            $distribution = $this->calculateDistribution($assignments);

            // Clear relevant cache
            $this->clearAssignmentCache($serviceId, $attendanceDate);

            return [
                'assigned_count' => count($absentMemberIds),
                'leaders_count' => count($validLeaderIds),
                'distribution' => $distribution,
                'message' => 'Absent members assigned successfully'
            ];
        });
    }

    private function buildAssignments(
        array $memberIds,
        array $leaderIds,
        int $serviceId,
        string $attendanceDate
    ): array {
        $assignments = [];
        $leaderCount = count($leaderIds);
        $timestamp = now();

        foreach ($memberIds as $index => $memberId) {
            $leaderIndex = $index % $leaderCount;

            $assignments[] = [
                'leader_id' => $leaderIds[$leaderIndex],
                'member_id' => $memberId,
                'service_id' => $serviceId,
                'attendance_date' => $attendanceDate,
                'status' => 'pending',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        return $assignments;
    }

    private function calculateDistribution(array $assignments): array
    {
        $distribution = [];

        foreach ($assignments as $assignment) {
            $leaderId = $assignment['leader_id'];

            if (!isset($distribution[$leaderId])) {
                $distribution[$leaderId] = 0;
            }

            $distribution[$leaderId]++;
        }

        return $distribution;
    }

    public function getMonthlyStats(string $mode = 'avg', ?int $year = null): array
    {
        $cacheKey = $this->generateCacheKey('monthly_stats', [
            'mode' => $mode,
            'year' => $year
        ]);

        return Cache::remember($cacheKey, self::CACHE_TTL * 2, function () use ($mode, $year) {
            $aggregates = [
                'avg' => 'ROUND(AVG(male), 2) as male, ROUND(AVG(female), 2) as female, ROUND(AVG(children), 2) as children',
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
        });
    }

    private function generateCacheKey(string $prefix, array $data = []): string
    {
        ksort($data);
        $hash = md5(json_encode($data));
        return "attendance_service:{$prefix}:{$hash}";
    }

    private function clearAttendanceCache(): void
    {
        Cache::flush();
    }
    private function clearAssignmentCache(?int $serviceId = null, ?string $date = null): void
    {
        if ($serviceId && $date) {
            $cacheKey = $this->generateCacheKey('assignment_stats', [
                'service_id' => $serviceId,
                'date' => $date
            ]);
            Cache::forget($cacheKey);
        }

        Cache::flush();
    }
}
// private const TIMEZONE = 'Africa/Lagos';
// private const CACHE_TTL = 300; // 5 minutes

// /**
//  * Get all attendance records with optimized queries and caching
//  */
// public function getAllAttendance(array $filters = []): Collection
// {
//     $cacheKey = $this->generateCacheKey('attendance:all', $filters);

//     return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters) {
//         $query = Attendance::query();

//         $query->when(
//             !empty($filters['service_id']),
//             fn($q) =>
//             $q->where('service_id', $filters['service_id'])
//         );

//         $query->when(
//             !empty($filters['status']),
//             fn($q) =>
//             $q->where('status', $filters['status'])
//         );

//         $query->when(
//             !empty($filters['mode']),
//             fn($q) =>
//             $q->where('mode', $filters['mode'])
//         );

//         $query->when(!empty($filters['attendance_date']), function ($q) use ($filters) {
//             $dates = is_array($filters['attendance_date'])
//                 ? $filters['attendance_date']
//                 : [$filters['attendance_date']];

//             return $q->whereIn('attendance_date', $dates);
//         });

//         return $query->with([
//             'user:id,first_name,last_name,email,phone_number',
//             'service:id,name,start_time,day_of_week,service_date'
//         ])
//             ->select([
//                 'id',
//                 'user_id',
//                 'service_id',
//                 'attendance_date',
//                 'status',
//                 'mode',
//                 'created_at'
//             ])
//             ->latest('attendance_date')
//             ->get();
//     });
// }

// /**
//  * Get paginated attendance history for a user
//  */
// public function getUserAttendanceHistory(User $user, int $perPage = 15): LengthAwarePaginator
// {
//     return $user->attendances()
//         ->with('service:id,name,start_time')
//         ->select([
//             'id',
//             'service_id',
//             'attendance_date',
//             'status',
//             'mode',
//             'created_at'
//         ])
//         ->latest('attendance_date')
//         ->latest('id')
//         ->paginate($perPage);
// }

// /**
//  * Mark all users without attendance record as absent
//  */
// public function markAbsentees(array $data): int
// {
//     $serviceId = $data['service_id'];
//     $attendanceDate = Carbon::parse($data['date'], self::TIMEZONE)->toDateString();

//     $markedUserIds = Attendance::where('service_id', $serviceId)
//         ->whereDate('attendance_date', $attendanceDate)
//         ->pluck('user_id');

//     $inserted = DB::table('attendances')->insertUsing(
//         ['user_id', 'service_id', 'attendance_date', 'status', 'mode', 'created_at', 'updated_at'],
//         DB::table('users')
//             ->whereNotIn('id', $markedUserIds)
//             ->select([
//                 'id as user_id',
//                 DB::raw("{$serviceId} as service_id"),
//                 DB::raw("'{$attendanceDate}' as attendance_date"),
//                 DB::raw("'absent' as status"),
//                 DB::raw("NULL as mode"),
//                 DB::raw("NOW() as created_at"),
//                 DB::raw("NOW() as updated_at")
//             ])
//     );

//     $this->clearAllAttendanceCache();

//     return $inserted;
// }

// /**
//  * Get absentees for a specific service and date
//  */
// public function getAbsentees(array $data): Collection
// {
//     $serviceId = (int) $data['service_id'];

//     $attendanceDate = Carbon::parse($data['date'], self::TIMEZONE)->toDateString();

//     $cacheKey = $this->generateCacheKey('absentees', [
//         'service_id' => $serviceId,
//         'date' => $attendanceDate,
//     ]);

//     return Cache::remember($cacheKey, self::CACHE_TTL, fn() => Attendance::with(['user:id,first_name,last_name,email,phone_number,gender'])
//         ->where('service_id', $serviceId)
//         ->whereDate('attendance_date', $attendanceDate)
//         ->where('status', 'absent')
//         ->select(['id', 'user_id', 'service_id', 'attendance_date', 'status', 'created_at'])
//         ->get());
// }

// /**
//  * Get monthly attendance statistics (average or total)
//  */
// public function getMonthlyStats(string $mode = 'avg', ?int $year = null): array
// {
//     $cacheKey = $this->generateCacheKey('monthly_stats', [
//         'mode' => $mode,
//         'year' => $year
//     ]);

//     return Cache::remember($cacheKey, self::CACHE_TTL * 2, function () use ($mode, $year) {
//         $aggregates = [
//             'avg' => 'ROUND(AVG(male), 2) as male, ROUND(AVG(female), 2) as female, ROUND(AVG(children), 2) as children',
//             'total' => 'SUM(male) as male, SUM(female) as female, SUM(children) as children',
//         ];

//         if (!isset($aggregates[$mode])) {
//             throw new \InvalidArgumentException('Invalid mode. Use avg or total.');
//         }

//         $query = DB::table('usher_attendances')
//             ->selectRaw("
//                 YEAR(service_date) as year,
//                 MONTH(service_date) as month_num,
//                 DATE_FORMAT(MIN(service_date), '%b') as month_label,
//                 {$aggregates[$mode]}
//             ");

//         if ($year) {
//             $query->whereYear('service_date', $year);
//         }

//         $results = $query
//             ->groupBy(DB::raw("YEAR(service_date), MONTH(service_date)"))
//             ->orderBy(DB::raw("YEAR(service_date), MONTH(service_date)"))
//             ->get();

//         $dataset = $results->map(function ($row) {
//             return [
//                 'year' => (int) $row->year,
//                 'month' => $row->month_label,
//                 'monthNum' => (int) $row->month_num,
//                 'male' => (float) $row->male,
//                 'female' => (float) $row->female,
//                 'children' => (float) $row->children,
//             ];
//         });

//         $series = [
//             ['type' => 'bar', 'xKey' => 'month', 'yKey' => 'male', 'yName' => 'Male'],
//             ['type' => 'bar', 'xKey' => 'month', 'yKey' => 'female', 'yName' => 'Female'],
//             ['type' => 'bar', 'xKey' => 'month', 'yKey' => 'children', 'yName' => 'Children'],
//         ];

//         return [
//             'dataset' => $dataset,
//             'series' => $series,
//             'mode' => $mode,
//         ];
//     });
// }

// /**
//  * Generate a consistent cache key from prefix and filters
//  */
// private function generateCacheKey(string $prefix, array $data = []): string
// {
//     ksort($data);
//     $hash = md5(json_encode($data));
//     return "attendance_service:{$prefix}:{$hash}";
// }

// private function clearAllAttendanceCache(): void
// {
//     Cache::flush();
// }
