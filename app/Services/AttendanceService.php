<?php

namespace App\Services;

use App\Exceptions\AttendanceException;
use App\Models\AbsenteeAssignment;
use App\Models\Attendance;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
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

            return $query->with(['user', 'service'])
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

    public function getUserAttendanceHistory(User $user, array $filters): Collection
    {
        return $user->attendances()
            ->with(['user', 'service'])
            ->when(
                !empty($filters['service_id']),
                fn($q) => $q->where('service_id', $filters['service_id'])
            )
            ->when(
                !empty($filters['status']),
                fn($q) => $q->where('status', $filters['status'])
            )
            ->when(
                !empty($filters['mode']),
                fn($q) => $q->where('mode', $filters['mode'])
            )
            ->when(!empty($filters['attendance_date']), function ($q) use ($filters) {
                $dates = is_array($filters['attendance_date'])
                    ? $filters['attendance_date']
                    : [$filters['attendance_date']];

                return $q->whereIn('attendance_date', $dates);
            })
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
            ->get();
    }

    public function markAbsentees(array $data): int
    {
        $serviceId = $data['service_id'];
        $attendanceDate = Carbon::parse($data['attendance_date'], self::TIMEZONE)->toDateString();

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
        $this->validateAssignmentData($data);

        $leaderIds = $data['leader_ids'];
        $serviceId = (int) $data['service_id'];
        $attendanceDate = Carbon::parse($data['attendance_date'], self::TIMEZONE)->toDateString();

        // Eager load and validate in one query
        $leaders = $this->validateAndGetLeaders($leaderIds);
        $this->validateService($serviceId);

        return DB::transaction(function () use ($leaders, $serviceId, $attendanceDate) {
            $absentMembers = $this->getAbsentMembers($serviceId, $attendanceDate);

            if ($absentMembers->isEmpty()) {
                throw new AttendanceException('No absent members found for this service');
            }

            $assignments = $this->buildAssignments(
                $absentMembers->pluck('id', 'user_id')->toArray(),
                $leaders->pluck('id')->toArray(),
                $serviceId,
                $attendanceDate
            );

            $this->upsertAssignments($assignments);

            $distribution = $this->calculateDistribution($assignments);

            $this->clearAssignmentCache($serviceId, $attendanceDate);

            $this->notifyLeaders($leaders);

            return [
                'assigned_count' => $absentMembers->count(),
                'leaders_count' => $leaders->count(),
                'distribution' => $distribution,
            ];
        });
    }

    private function validateAssignmentData(array $data): void
    {
        if (empty($data['leader_ids']) || !is_array($data['leader_ids'])) {
            throw new AttendanceException('Leader IDs must be a non-empty array');
        }

        if (empty($data['service_id'])) {
            throw new AttendanceException('Service ID is required');
        }

        if (empty($data['attendance_date'])) {
            throw new AttendanceException('Attendance date is required');
        }
    }

    private function validateAndGetLeaders(array $leaderIds): Collection
    {
        $leaders = User::whereIn('id', $leaderIds)
            ->select('id', 'last_name', 'email')
            ->get();

        if ($leaders->count() !== count($leaderIds)) {
            $foundIds = $leaders->pluck('id')->toArray();
            $missingIds = array_diff($leaderIds, $foundIds);

            throw new AttendanceException(
                'Invalid leader IDs: ' . implode(', ', $missingIds)
            );
        }

        return $leaders;
    }

    private function validateService(int $serviceId): void
    {
        if (!Cache::remember("service_exists_{$serviceId}", 3600, fn() => Service::where('id', $serviceId)->exists())) {
            throw new AttendanceException("Service with ID {$serviceId} not found");
        }
    }

    private function getAbsentMembers(int $serviceId, string $attendanceDate): Collection
    {
        return Attendance::where('service_id', $serviceId)
            ->whereDate('attendance_date', $attendanceDate)
            ->where('status', 'absent')
            ->select('id', 'user_id')
            ->get();
    }

    private function upsertAssignments(array $assignments): void
    {
        if (empty($assignments)) {
            return;
        }
        // Add timestamps to all assignments
        $now = now();
        $assignments = array_map(fn($assignment) => array_merge($assignment, [
            'created_at' => $now,
            'updated_at' => $now,
        ]), $assignments);

        AbsenteeAssignment::upsert(
            $assignments,
            ['service_id', 'attendance_date', 'user_id'], // Unique keys
            ['leader_id', 'attendance_id', 'updated_at'] // Columns to update
        );
    }

    private function notifyLeaders(Collection $leaders): void
    {
        $leaderData = $leaders->map(fn($leader) => [
            'email' => $leader->email,
            'name' => $leader->last_name,
        ])->toArray();

        app(MailService::class)->sendAbsentMemberAssignmentEmail($leaderData);
    }

    private function buildAssignments(
        array $members,
        array $leaderIds,
        int $serviceId,
        string $attendanceDate
    ): array {
        $assignments = [];
        $leaderCount = count($leaderIds);
        $timestamp = now();

        $i = 0;
        foreach ($members as $userId => $attendanceId) {
            $leaderIndex = $i % $leaderCount;

            $assignments[] = [
                'attendance_id' => $attendanceId,
                'leader_id' => $leaderIds[$leaderIndex],
                'user_id' => $userId,
                'service_id' => $serviceId,
                'attendance_date' => $attendanceDate,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
            $i++;
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

    public function getUserAttendanceMetrics(User $user, ?int $month = null, ?int $year = null): array
    {
        $month ??= now()->month;
        $year ??= now()->year;

        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        $attendanceStats = DB::table('attendances')
            ->select([
                DB::raw('COUNT(CASE WHEN status = "present" THEN 1 END) as total_present'),
                DB::raw('COUNT(CASE WHEN status = "absent" THEN 1 END) as total_absent'),
                DB::raw('COUNT(*) as total_recorded')
            ])
            ->where('user_id', $user->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->first();

        // Calculate total expected services for the month
        $totalServices = $this->calculateTotalServicesForMonth($startDate, $endDate);

        // Calculate percentages
        $presentPercentage = $totalServices > 0
            ? round(($attendanceStats->total_present / $totalServices) * 100, 2)
            : 0;

        $absentPercentage = $totalServices > 0
            ? round(($attendanceStats->total_absent / $totalServices) * 100, 2)
            : 0;

        if ($presentPercentage == 100 && $totalServices > 0) {
            $this->awardAttendanceBadge($user, $month, $year);
        }

        return [
            'month' => $month,
            'year' => $year,
            'total_present' => $attendanceStats->total_present ?? 0,
            'total_absent' => $attendanceStats->total_absent ?? 0,
            'total_services' => $totalServices,
            'present_percentage' => $presentPercentage,
            'absent_percentage' => $absentPercentage,
            'attendance_rate' => $presentPercentage,
        ];
    }

    protected function awardAttendanceBadge(User $user, int $month, int $year): bool
    {
        $affected = User::where('id', $user->id)
            ->where(fn($q) => $q->where('last_badge_month', '!=', $month)
                ->orWhere('last_badge_year', '!=', $year)
                ->orWhereNull('last_badge_month'))
            ->update([
                'attendance_badge' => DB::raw('attendance_badge + 1'),
                'last_badge_month' => $month,
                'last_badge_year' => $year,
            ]);
        return $affected;
    }

    private function calculateTotalServicesForMonth(Carbon $startDate, Carbon $endDate): int
    {
        $standardServiceDays = $this->countDayOfWeekOccurrences($startDate, $endDate, [
            \Carbon\CarbonInterface::TUESDAY,
            \Carbon\CarbonInterface::FRIDAY,
            \Carbon\CarbonInterface::SUNDAY
        ]);
        $customServiceDays = Service::whereBetween('service_date', [$startDate, $endDate])
            ->whereNotIn(DB::raw('DAYOFWEEK(service_date)'), [1, 3, 6])
            ->count();

        return $standardServiceDays + $customServiceDays;
    }

    private function countDayOfWeekOccurrences(Carbon $startDate, Carbon $endDate, array $daysOfWeek): int
    {
        $count = 0;
        $current = $startDate->copy();

        while ($current <= $endDate) {
            if (in_array($current->dayOfWeek, $daysOfWeek)) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
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
