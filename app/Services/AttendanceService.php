<?php

namespace App\Services;

use App\Exceptions\AttendanceException;
use App\Models\AbsenteeAssignment;
use App\Models\Attendance;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    private const TIMEZONE = 'Africa/Lagos';

    // ==================== ATTENDANCE RETRIEVAL ====================
    /**
     * Get all attendance records with optional filters
     */
    public function getAllAttendance(array $filters = []): LengthAwarePaginator //Collection
    {
        $query = Attendance::query()
            ->with(['user:id,first_name,last_name,email,gender,avatar,phone_number', 'service'])
            ->select([
                'id',
                'user_id',
                'service_id',
                'attendance_date',
                'status',
                'mode',
                'created_at'
            ]);

        $this->applyAttendanceFilters($query, $filters);

        return $query->latest('attendance_date')
            ->latest('id')
            ->paginate(200);
    }

    /**
     * Get user's attendance history
     */
    public function getUserAttendanceHistory(User $user, array $filters): Collection
    {
        $query = $user->attendances()
            ->with(['service', 'user'])
            ->select([
                'id',
                'user_id',
                'service_id',
                'attendance_date',
                'status',
                'mode',
                'created_at'
            ]);

        $this->applyAttendanceFilters($query, $filters);

        return $query->latest('attendance_date')
            ->latest('id')
            ->get();
    }

    /**
     * Apply filters to attendance query
     */
    private function applyAttendanceFilters($query, array $filters): void
    {
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
    }

    // ==================== ATTENDANCE MARKING ====================
    /**
     * Mark attendance for a user
     */
    public function markUserAttendance(User $user, array $data): Attendance
    {
        $service = Service::findOrFail($data['service_id']);
        $attendanceDate = $this->getServiceDate($service);

        return $this->upsertAttendance([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'attendance_date' => $attendanceDate,
            'status' => $data['status'],
            'mode' => $data['status'] === 'present' ? $data['mode'] : null,
        ]);
    }

    /**
     * Bulk mark attendance for multiple users
     */
    public function bulkMarkAttendance(array $data): void
    {
        $attendanceDate = Carbon::parse($data['attendance_date'])->toDateString();
        $serviceId = $data['service_id'];

        DB::transaction(function () use ($data, $attendanceDate, $serviceId) {
            foreach ($data['attendances'] as $attendance) {
                Attendance::updateOrCreate(
                    [
                        'user_id' => $attendance['user_id'],
                        'service_id' => $serviceId,
                        'attendance_date' => $attendanceDate,
                    ],
                    [
                        'status' => $attendance['status'],
                        'mode' => $attendance['mode'] ?? null,
                    ]
                );
            }
        });
    }

    /**
     * Create or update attendance record
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

        return $attendance->load(['user:id,first_name,last_name,email,gender,avatar,phone_number', 'service']);
    }

    /**
     * Get service date
     */
    private function getServiceDate(Service $service): string
    {
        return $service->service_date ?? Carbon::now(self::TIMEZONE)->toDateString();
    }

    // ==================== ABSENTEE MANAGEMENT ====================
    /**
     * Mark all unmarked members as absent
     */
    public function markAbsentees(array $data): int
    {
        $serviceId = $data['service_id'];
        $attendanceDate = Carbon::parse($data['attendance_date'], self::TIMEZONE)->toDateString();

        $markedUserIds = Attendance::where('service_id', $serviceId)
            ->whereDate('attendance_date', $attendanceDate)
            ->pluck('user_id');

        return DB::table('attendances')->insertUsing(
            ['user_id', 'service_id', 'attendance_date', 'status', 'mode', 'created_at', 'updated_at'],
            User::members()->whereNotIn('id', $markedUserIds)
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
    }

    /**
     * Get absent members for a service
     */
    private function getAbsentMembers(int $serviceId, string $attendanceDate): Collection
    {
        return Attendance::where('service_id', $serviceId)
            ->whereDate('attendance_date', $attendanceDate)
            ->where('status', 'absent')
            ->select('id', 'user_id')
            ->get();
    }

    // ==================== LEADER ASSIGNMENT ====================
    /**
     * Assign absent members to leaders for follow-up
     */
    public function assignAbsenteesToLeaders(array $data): array
    {
        $this->validateAssignmentData($data);

        $leaderIds = $data['leader_ids'];
        $serviceId = (int) $data['service_id'];
        $attendanceDate = Carbon::parse($data['attendance_date'], self::TIMEZONE)->toDateString();

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

            $this->notifyLeaders($leaders);

            return [
                'assigned_count' => $absentMembers->count(),
                'leaders_count' => $leaders->count(),
                'distribution' => $distribution,
            ];
        });
    }

    /**
     * Validate assignment data
     */
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

    /**
     * Validate and get leaders
     */
    private function validateAndGetLeaders(array $leaderIds): Collection
    {
        $leaders = User::whereIn('id', $leaderIds)->select('id', 'first_name', 'last_name', 'email')->get();

        if ($leaders->count() !== count($leaderIds)) {
            $foundIds = $leaders->pluck('id')->toArray();
            $missingIds = array_diff($leaderIds, $foundIds);
            throw new AttendanceException('Invalid leader IDs: ' . implode(', ', $missingIds));
        }

        return $leaders;
    }

    /**
     * Validate service exists
     */
    private function validateService(int $serviceId): void
    {
        if (!Service::where('id', $serviceId)->exists()) {
            throw new AttendanceException("Service with ID {$serviceId} not found");
        }
    }

    /**
     * Build assignments array
     */
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

    /**
     * Upsert assignments
     */
    private function upsertAssignments(array $assignments): void
    {
        if (empty($assignments)) {
            return;
        }

        AbsenteeAssignment::upsert(
            $assignments,
            ['service_id', 'attendance_date', 'user_id'],
            ['leader_id', 'attendance_id', 'updated_at']
        );
    }

    /**
     * Calculate distribution of assignments
     */
    private function calculateDistribution(array $assignments): array
    {
        $distribution = [];

        foreach ($assignments as $assignment) {
            $leaderId = $assignment['leader_id'];
            $distribution[$leaderId] = ($distribution[$leaderId] ?? 0) + 1;
        }

        return $distribution;
    }

    /**
     * Notify leaders about assignments
     */
    private function notifyLeaders(Collection $leaders): void
    {
        $leaderData = $leaders->map(fn($leader) => [
            'email' => $leader->email,
            'name' => $leader->first_name . ' ' . $leader->last_name,
        ])->toArray();

        app(MailService::class)->sendAbsentMemberAssignmentEmail($leaderData);
    }

    // ==================== STATISTICS & METRICS ====================
    /**
     * Get monthly average attendance
     */
    public function getMonthlyAverageAttendance(?int $year = null): array
    {
        $query = DB::table('usher_attendances')
            ->selectRaw('
                MONTH(service_date) as month_num,
                ROUND(AVG(children), 2) as children,
                ROUND(AVG(female), 2) as women,
                ROUND(AVG(male), 2) as men
            ')
            ->when($year, fn($q) => $q->whereYear('service_date', $year))
            ->groupBy(DB::raw('MONTH(service_date)'))
            ->orderBy('month_num')
            ->get();

        $months = [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December'
        ];

        return $query->map(fn($row) => [
            'month' => $months[$row->month_num],
            'children' => (float) $row->children,
            'women' => (float) $row->women,
            'men' => (float) $row->men,
        ])->values()->all();
    }

    /**
     * Get user's attendance metrics for a specific month
     */
    public function getUserAttendanceMetrics(User $user, ?int $month = null, ?int $year = null): array
    {
        $month ??= now()->month;
        $year ??= now()->year;

        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        $attendanceStats = $user->attendances()
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->selectRaw('
                COUNT(CASE WHEN status = "present" THEN 1 END) as total_present,
                COUNT(CASE WHEN status = "absent" THEN 1 END) as total_absent,
                COUNT(*) as total_recorded
            ')
            ->first();

        $totalServices = $this->calculateTotalServicesForMonth($startDate, $endDate);

        $presentPercentage = $totalServices > 0
            ? round(($attendanceStats->total_present / $totalServices) * 100, 2)
            : 0;

        $absentPercentage = $totalServices > 0
            ? round(($attendanceStats->total_absent / $totalServices) * 100, 2)
            : 0;

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

    /**
     * Calculate total services for a month
     */
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

    /**
     * Count occurrences of specific days of week
     */
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

    // ==================== BADGE MANAGEMENT ====================
    /**
     * Award monthly badges to users with perfect attendance
     */
    public function awardMonthlyBadges(?int $year = null, ?int $month = null): array
    {
        $year ??= now()->subMonth()->year;
        $month ??= now()->subMonth()->month;

        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        $totalServiceDays = $this->calculateTotalServicesForMonth($startDate, $endDate);

        if ($totalServiceDays === 0) {
            return [
                'success' => false,
                'message' => 'No service days found',
            ];
        }

        $customServiceDates = Service::whereBetween('service_date', [$startDate, $endDate])
            ->whereNotIn(DB::raw('DAYOFWEEK(service_date)'), [1, 3, 6])
            ->pluck('service_date')
            ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
            ->toArray();

        $eligibleUserIds = $this->getEligibleUsers($year, $month, $totalServiceDays, $customServiceDates);

        foreach ($eligibleUserIds as $userId) {
            $user = User::find($userId);
            $this->awardBadge($user, $year, $month);
        }

        return [
            'success' => true,
            'message' => count($eligibleUserIds) > 0
                ? count($eligibleUserIds) . ' Badge(s) awarded successfully'
                : 'No users qualified for badges',
        ];
    }

    /**
     * Award badge to a user
     */
    public function awardBadge(User $user, int $year, int $month): bool
    {
        $badges = $user->attendance_badges ?? collect([]);
        $exists = $badges->contains(fn($badge) => $badge['year'] === $year && $badge['month'] === $month);

        if ($exists) {
            return false;
        }

        $badges->push([
            'year' => $year,
            'month' => $month,
            'month_name' => Carbon::create($year, $month)->format('F'),
            'awarded_at' => now(),
        ]);

        $user->attendance_badges = $badges;
        $user->save();
        return true;
    }

    /**
     * Get users eligible for badges
     */
    protected function getEligibleUsers(int $year, int $month, int $totalServiceDays, array $customServiceDates): array
    {
        $query = DB::table('attendances')
            ->select('user_id')
            ->whereYear('attendance_date', $year)
            ->whereMonth('attendance_date', $month)
            ->where('status', 'present');

        if (!empty($customServiceDates)) {
            $query->where(function ($q) use ($customServiceDates) {
                $q->whereRaw('DAYOFWEEK(attendance_date) IN (1, 3, 6)')
                    ->orWhereIn(DB::raw('DATE(attendance_date)'), $customServiceDates);
            });
        } else {
            $query->whereRaw('DAYOFWEEK(attendance_date) IN (1, 3, 6)');
        }

        return $query->groupBy('user_id')
            ->havingRaw('COUNT(DISTINCT attendance_date) >= ?', [$totalServiceDays])
            ->pluck('user_id')
            ->toArray();
    }

    /**
     * Get top 3 attendees for a month
     */
    public function getTopAttendees(int $month, int $year): array
    {
        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth();

        $totalServices = $this->calculateTotalServicesForMonth($startOfMonth, $endOfMonth);

        if ($totalServices === 0) {
            return [];
        }

        $members = User::members()
            ->withCount([
                'attendances as present_count' => function ($query) use ($startOfMonth, $endOfMonth) {
                    $query->whereBetween('attendance_date', [$startOfMonth, $endOfMonth])
                        ->where('status', 'present');
                }
            ])
            ->having('present_count', '>', 0)
            ->orderByDesc('present_count')
            ->inRandomOrder()
            ->limit(3)
            ->get()
            ->map(function ($member, $index) use ($totalServices) {
                $attendancePercentage = round(($member->present_count / $totalServices) * 100);

                return [
                    'id' => $member->id,
                    'full_name' => trim("{$member->first_name} {$member->last_name}"),
                    'avatar' => $member->avatar,
                    'initials' => strtoupper(substr($member->first_name, 0, 1) . substr($member->last_name, 0, 1)),
                    'attendance_percentage' => (int) $attendancePercentage,
                    'total_services' => $totalServices,
                    'present' => (int) $member->present_count,
                    'position' => $index + 1,
                ];
            })
            ->toArray();

        return $members;
    }
}
