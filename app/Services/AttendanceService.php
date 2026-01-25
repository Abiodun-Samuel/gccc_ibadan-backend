<?php

namespace App\Services;

use App\Exceptions\AttendanceException;
use App\Models\AbsenteeAssignment;
use App\Models\Attendance;
use App\Models\Service;
use App\Models\User;
// use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

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
        $isNowPresent = $data['status'] === 'present';
        if ($isNowPresent) {
            $user->increment('total_stars', $service->reward_stars);
        }
        $attendance = $this->upsertAttendance([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'attendance_date' => $attendanceDate,
            'status' => $data['status'],
            'mode' => $data['status'] === 'present' ? $data['mode'] : null,
        ]);
        return $attendance->load(['service', 'user']);
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
        return $attendance;
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

        $providedLeaderIds = $data['leader_ids'];
        $serviceId = (int) $data['service_id'];
        $attendanceDate = Carbon::parse($data['attendance_date'], self::TIMEZONE)->toDateString();

        $this->validateLeaders($providedLeaderIds);
        $this->validateService($serviceId);

        return DB::transaction(function () use ($providedLeaderIds, $serviceId, $attendanceDate) {
            // Clear previous assignments for this service and date
            $this->clearPreviousAssignments($serviceId, $attendanceDate);

            // Get absent members with their units and leaders
            $absentMembers = $this->getAbsentMembersWithUnits($serviceId, $attendanceDate);

            if ($absentMembers->isEmpty()) {
                throw new AttendanceException('No absent members found for this service');
            }

            // Separate glory team members from regular members
            [$gloryTeamMembers, $regularMembers] = $this->separateAbsentees($absentMembers);

            // Build assignment pool with leader workload tracking
            $leaderWorkload = $this->initializeLeaderWorkload($providedLeaderIds);

            $assignments = [];

            // Assign glory team members to their unit leaders (balanced)
            if ($gloryTeamMembers->isNotEmpty()) {
                $gloryAssignments = $this->buildBalancedGloryTeamAssignments(
                    $gloryTeamMembers,
                    $serviceId,
                    $attendanceDate,
                    $leaderWorkload
                );
                $assignments = array_merge($assignments, $gloryAssignments);
            }

            // Assign regular members evenly to all leaders (balanced)
            if ($regularMembers->isNotEmpty()) {
                $regularAssignments = $this->buildBalancedRegularAssignments(
                    $regularMembers,
                    $serviceId,
                    $attendanceDate,
                    $leaderWorkload
                );
                $assignments = array_merge($assignments, $regularAssignments);
            }

            if (empty($assignments)) {
                throw new AttendanceException('No valid assignments could be created');
            }

            $this->insertAssignments($assignments);

            $distribution = $this->calculateDistribution($assignments);
            $assignedLeaders = $this->getAssignedLeaders($assignments);

            $this->notifyLeaders($assignedLeaders);

            return [
                'assigned_count' => count($assignments),
                'glory_team_count' => count($gloryAssignments ?? []),
                'regular_count' => count($regularAssignments ?? []),
                'leaders_count' => count($assignedLeaders),
                'distribution' => $distribution,
                'workload_balance' => $this->getWorkloadStats($distribution),
            ];
        });
    }

    /**
     * Clear previous assignments for the given service and date
     */
    private function clearPreviousAssignments(int $serviceId, string $attendanceDate): void
    {
        AbsenteeAssignment::where('service_id', $serviceId)
            ->where('attendance_date', $attendanceDate)
            ->delete();
    }

    /**
     * Get absent members with their glory team status and unit leaders
     * Optimized query with proper indexing strategy
     */
    private function getAbsentMembersWithUnits(int $serviceId, string $attendanceDate): Collection
    {
        // Main query - fetch absent members efficiently
        $absentMembers = DB::table('attendances')
            ->join('users', 'attendances.user_id', '=', 'users.id')
            ->where('attendances.service_id', $serviceId)
            ->where('attendances.attendance_date', $attendanceDate)
            ->where('attendances.status', 'absent')
            ->whereNotIn('users.status', ['disabled', 'deactivated'])
            ->select([
                'attendances.id as attendance_id',
                'attendances.user_id',
                'users.is_glory_team_member',
            ])
            ->get();

        if ($absentMembers->isEmpty()) {
            return collect();
        }

        $userIds = $absentMembers->pluck('user_id')->unique()->toArray();

        // Fetch unit relationships in a single query
        $unitMemberships = DB::table('unit_user')
            ->join('units', 'unit_user.unit_id', '=', 'units.id')
            ->whereIn('unit_user.user_id', $userIds)
            ->select([
                'unit_user.user_id',
                'units.leader_id',
                'units.assistant_leader_id',
                'units.assistant_leader_id_2',
            ])
            ->get()
            ->groupBy('user_id');

        // Map unit leaders to each absent member
        return $absentMembers->map(function ($member) use ($unitMemberships) {
            // Convert stdClass to object with unit_leaders property
            $memberObj = (object) [
                'attendance_id' => $member->attendance_id,
                'user_id' => $member->user_id,
                'is_glory_team_member' => (bool) $member->is_glory_team_member,
                'unit_leaders' => collect(),
            ];

            if (isset($unitMemberships[$member->user_id])) {
                $leaders = collect();
                foreach ($unitMemberships[$member->user_id] as $unit) {
                    if ($unit->leader_id) $leaders->push($unit->leader_id);
                    if ($unit->assistant_leader_id) $leaders->push($unit->assistant_leader_id);
                    if ($unit->assistant_leader_id_2) $leaders->push($unit->assistant_leader_id_2);
                }
                $memberObj->unit_leaders = $leaders->unique()->values();
            }

            return $memberObj;
        });
    }

    /**
     * Separate absent members into glory team and regular members
     */
    private function separateAbsentees(Collection $absentMembers): array
    {
        $gloryTeam = $absentMembers->filter(fn($member) => $member->is_glory_team_member);
        $regular = $absentMembers->reject(fn($member) => $member->is_glory_team_member);

        return [$gloryTeam, $regular];
    }

    /**
     * Initialize leader workload tracking
     */
    private function initializeLeaderWorkload(array $leaderIds): array
    {
        return array_fill_keys($leaderIds, 0);
    }

    /**
     * Build balanced assignments for glory team members to their unit leaders
     * Uses least-loaded leader strategy for fairness
     */
    private function buildBalancedGloryTeamAssignments(
        Collection $gloryTeamMembers,
        int $serviceId,
        string $attendanceDate,
        array &$leaderWorkload
    ): array {
        $assignments = [];
        $timestamp = now();

        foreach ($gloryTeamMembers as $member) {
            $unitLeaders = $this->getUnitLeaders($member);

            if ($unitLeaders->isEmpty()) {
                continue; // Skip if no unit leaders (shouldn't happen per business rules)
            }

            // Find the least loaded leader among this member's unit leaders
            $selectedLeaderId = $this->getLeastLoadedLeader($unitLeaders->toArray(), $leaderWorkload);

            $assignments[] = [
                'attendance_id' => $member->attendance_id,
                'leader_id' => $selectedLeaderId,
                'user_id' => $member->user_id,
                'service_id' => $serviceId,
                'attendance_date' => $attendanceDate,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            // Update workload
            $leaderWorkload[$selectedLeaderId]++;
        }

        return $assignments;
    }

    /**
     * Extract unit leaders from pre-loaded data
     */
    private function getUnitLeaders($member): Collection
    {
        return $member->unit_leaders ?? collect();
    }

    /**
     * Get the least loaded leader from available leaders
     * This ensures even distribution and prevents burden
     */
    private function getLeastLoadedLeader(array $availableLeaders, array $leaderWorkload): int
    {
        $minLoad = PHP_INT_MAX;
        $selectedLeader = null;

        foreach ($availableLeaders as $leaderId) {
            $currentLoad = $leaderWorkload[$leaderId] ?? 0;

            if ($currentLoad < $minLoad) {
                $minLoad = $currentLoad;
                $selectedLeader = $leaderId;
            }
        }

        return $selectedLeader;
    }

    /**
     * Build balanced assignments for regular members
     * Distributes evenly across all available leaders
     */
    private function buildBalancedRegularAssignments(
        Collection $regularMembers,
        int $serviceId,
        string $attendanceDate,
        array &$leaderWorkload
    ): array {
        $assignments = [];
        $timestamp = now();

        foreach ($regularMembers as $member) {
            // Get all available leaders
            $availableLeaders = array_keys($leaderWorkload);

            // Assign to least loaded leader
            $selectedLeaderId = $this->getLeastLoadedLeader($availableLeaders, $leaderWorkload);

            $assignments[] = [
                'attendance_id' => $member->attendance_id,
                'leader_id' => $selectedLeaderId,
                'user_id' => $member->user_id,
                'service_id' => $serviceId,
                'attendance_date' => $attendanceDate,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            // Update workload
            $leaderWorkload[$selectedLeaderId]++;
        }

        return $assignments;
    }

    /**
     * Insert assignments in bulk with chunking for large datasets
     */
    private function insertAssignments(array $assignments): void
    {
        if (empty($assignments)) {
            return;
        }

        // Use chunk to avoid memory issues with large datasets
        collect($assignments)->chunk(500)->each(function ($chunk) {
            AbsenteeAssignment::insert($chunk->toArray());
        });
    }

    /**
     * Get unique leaders who received assignments
     */
    private function getAssignedLeaders(array $assignments): Collection
    {
        $leaderIds = collect($assignments)->pluck('leader_id')->unique();

        return User::whereIn('id', $leaderIds)
            ->select('id', 'first_name', 'last_name', 'email')
            ->get();
    }

    /**
     * Calculate distribution of assignments per leader
     */
    private function calculateDistribution(array $assignments): array
    {
        $distribution = [];

        foreach ($assignments as $assignment) {
            $leaderId = $assignment['leader_id'];
            $distribution[$leaderId] = ($distribution[$leaderId] ?? 0) + 1;
        }

        // Sort by leader ID for consistent output
        ksort($distribution);

        return $distribution;
    }

    /**
     * Get workload statistics for monitoring balance
     */
    private function getWorkloadStats(array $distribution): array
    {
        if (empty($distribution)) {
            return [
                'min' => 0,
                'max' => 0,
                'avg' => 0,
                'variance' => 0,
            ];
        }

        $values = array_values($distribution);
        $min = min($values);
        $max = max($values);
        $avg = round(array_sum($values) / count($values), 2);

        // Calculate variance to measure balance
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $avg, 2);
        }
        $variance = count($values) > 0 ? round($variance / count($values), 2) : 0;

        return [
            'min' => $min,
            'max' => $max,
            'avg' => $avg,
            'variance' => $variance, // Lower is better (more balanced)
        ];
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
     * Validate leaders exist
     */
    private function validateLeaders(array $leaderIds): void
    {
        $existingCount = User::whereIn('id', $leaderIds)->count();

        if ($existingCount !== count($leaderIds)) {
            throw new AttendanceException('One or more invalid leader IDs provided');
        }
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
     * Notify leaders about assignments
     */
    private function notifyLeaders(Collection $leaders): void
    {
        if ($leaders->isEmpty()) {
            return;
        }

        $leaderData = $leaders->map(fn($leader) => [
            'email' => $leader->email,
            'name' => trim($leader->first_name . ' ' . $leader->last_name),
        ])->toArray();

        app(MailService::class)->sendAbsentMemberAssignmentEmail($leaderData);
    }
    // public function assignAbsenteesToLeaders(array $data): array
    // {
    //     $this->validateAssignmentData($data);

    //     $leaderIds = $data['leader_ids'];
    //     $serviceId = (int) $data['service_id'];
    //     $attendanceDate = Carbon::parse($data['attendance_date'], self::TIMEZONE)->toDateString();

    //     $leaders = $this->validateAndGetLeaders($leaderIds);
    //     $this->validateService($serviceId);

    //     return DB::transaction(function () use ($leaders, $serviceId, $attendanceDate) {
    //         $absentMembers = $this->getAbsentMembers($serviceId, $attendanceDate);

    //         if ($absentMembers->isEmpty()) {
    //             throw new AttendanceException('No absent members found for this service');
    //         }

    //         $assignments = $this->buildAssignments(
    //             $absentMembers->pluck('id', 'user_id')->toArray(),
    //             $leaders->pluck('id')->toArray(),
    //             $serviceId,
    //             $attendanceDate
    //         );

    //         $this->upsertAssignments($assignments);

    //         $distribution = $this->calculateDistribution($assignments);

    //         $this->notifyLeaders($leaders);

    //         return [
    //             'assigned_count' => $absentMembers->count(),
    //             'leaders_count' => $leaders->count(),
    //             'distribution' => $distribution,
    //         ];
    //     });
    // }

    // /**
    //  * Validate assignment data
    //  */
    // private function validateAssignmentData(array $data): void
    // {
    //     if (empty($data['leader_ids']) || !is_array($data['leader_ids'])) {
    //         throw new AttendanceException('Leader IDs must be a non-empty array');
    //     }

    //     if (empty($data['service_id'])) {
    //         throw new AttendanceException('Service ID is required');
    //     }

    //     if (empty($data['attendance_date'])) {
    //         throw new AttendanceException('Attendance date is required');
    //     }
    // }

    // /**
    //  * Validate and get leaders
    //  */
    // private function validateAndGetLeaders(array $leaderIds): Collection
    // {
    //     $leaders = User::whereIn('id', $leaderIds)->select('id', 'first_name', 'last_name', 'email')->get();

    //     if ($leaders->count() !== count($leaderIds)) {
    //         $foundIds = $leaders->pluck('id')->toArray();
    //         $missingIds = array_diff($leaderIds, $foundIds);
    //         throw new AttendanceException('Invalid leader IDs: ' . implode(', ', $missingIds));
    //     }

    //     return $leaders;
    // }

    // /**
    //  * Validate service exists
    //  */
    // private function validateService(int $serviceId): void
    // {
    //     if (!Service::where('id', $serviceId)->exists()) {
    //         throw new AttendanceException("Service with ID {$serviceId} not found");
    //     }
    // }

    // /**
    //  * Build assignments array
    //  */
    // private function buildAssignments(
    //     array $members,
    //     array $leaderIds,
    //     int $serviceId,
    //     string $attendanceDate
    // ): array {
    //     $assignments = [];
    //     $leaderCount = count($leaderIds);
    //     $timestamp = now();

    //     $i = 0;
    //     foreach ($members as $userId => $attendanceId) {
    //         $leaderIndex = $i % $leaderCount;

    //         $assignments[] = [
    //             'attendance_id' => $attendanceId,
    //             'leader_id' => $leaderIds[$leaderIndex],
    //             'user_id' => $userId,
    //             'service_id' => $serviceId,
    //             'attendance_date' => $attendanceDate,
    //             'created_at' => $timestamp,
    //             'updated_at' => $timestamp,
    //         ];
    //         $i++;
    //     }

    //     return $assignments;
    // }

    // /**
    //  * Upsert assignments
    //  */
    // private function upsertAssignments(array $assignments): void
    // {
    //     if (empty($assignments)) {
    //         return;
    //     }

    //     AbsenteeAssignment::upsert(
    //         $assignments,
    //         ['service_id', 'attendance_date', 'user_id'],
    //         ['leader_id', 'attendance_id', 'updated_at']
    //     );
    // }

    // /**
    //  * Calculate distribution of assignments
    //  */
    // private function calculateDistribution(array $assignments): array
    // {
    //     $distribution = [];

    //     foreach ($assignments as $assignment) {
    //         $leaderId = $assignment['leader_id'];
    //         $distribution[$leaderId] = ($distribution[$leaderId] ?? 0) + 1;
    //     }

    //     return $distribution;
    // }

    // /**
    //  * Notify leaders about assignments
    //  */
    // private function notifyLeaders(Collection $leaders): void
    // {
    //     $leaderData = $leaders->map(fn($leader) => [
    //         'email' => $leader->email,
    //         'name' => $leader->first_name . ' ' . $leader->last_name,
    //     ])->toArray();

    //     app(MailService::class)->sendAbsentMemberAssignmentEmail($leaderData);
    // }

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
