<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attendance\MarkAbsenteesRequest;
use App\Http\Requests\Attendance\MarkAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\UsherAttendance;
use App\Models\FollowUpStatus;
use App\Enums\RoleEnum;
use App\Http\Resources\UserResource;
use App\Models\AbsenteeAssignment;
use App\Models\Attendance;
use App\Models\FollowupFeedback;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $attendanceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'service_id',
            'attendance_date',
            'status',
            'mode'
        ]);

        $attendance = $this->attendanceService->getAllAttendance($filters);

        return $this->successResponse(
            AttendanceResource::collection($attendance),
            'Attendance records retrieved successfully'
        );
    }
    protected function getAttendanceMessage($attendance): string
    {
        if ($attendance->status !== 'present') {
            return 'Attendance marked successfully.';
        }

        $stars = $attendance->service->reward_stars ?? 5;

        return sprintf(
            'Congratulations! You have earned %d %s for attending %s. Keep up the great work! ⭐',
            $stars,
            $stars === 1 ? 'star' : 'stars',
            $attendance->service->name
        );
    }
    public function markAttendance(MarkAttendanceRequest $request): JsonResponse
    {
        $user =  $request->user();
        $attendance = $this->attendanceService->markUserAttendance(
            $user,
            $request->validated()
        );
        $message = $this->getAttendanceMessage($attendance);

        return $this->successResponse(
            ['user' => UserResource::make($user->loadFullProfile())],
            $message,
            Response::HTTP_CREATED
        );
    }

    public function history(Request $request): JsonResponse
    {
        $filters = $request->only([
            'service_id',
            'attendance_date',
            'status',
            'mode'
        ]);

        $history = $this->attendanceService->getUserAttendanceHistory(
            $request->user(),
            $filters
        );

        return $this->successResponse(
            AttendanceResource::collection($history),
            'Attendance history retrieved successfully'
        );
    }

    public function markAbsentees(MarkAbsenteesRequest $request): JsonResponse
    {
        $inserted = $this->attendanceService->markAbsentees($request->validated());

        return $this->successResponse(
            ['marked_absent_count' => $inserted],
            'All absent users have been marked',
            Response::HTTP_CREATED
        );
    }

    public function adminMarkAttendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'attendance_date' => 'required|date',
            'attendances' => 'required|array|min:1',
            'attendances.*.user_id' => 'required|exists:users,id',
            'attendances.*.status' => 'required|in:present,absent',
            'attendances.*.mode' => 'nullable|in:online,onsite',
        ]);

        $this->attendanceService->bulkMarkAttendance($validated);

        return $this->successResponse(
            [],
            'Attendance updated successfully for selected users'
        );
    }

    public function assignAbsenteesToLeaders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'attendance_date' => ['required', 'date'],
            'leader_ids' => ['required', 'array', 'min:1'],
            'leader_ids.*' => ['exists:users,id'],
        ]);

        $result = $this->attendanceService->assignAbsenteesToLeaders($validated);

        return $this->successResponse(
            $result,
            "{$result['assigned_count']} absent members assigned to {$result['leaders_count']} leaders successfully",
            Response::HTTP_CREATED
        );
    }

    public function getAdminAttendanceMonthlyStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'nullable|integer|min:2020|max:2100',
        ]);

        $data = $this->attendanceService->getMonthlyAverageAttendance($validated['year'] ?? null);

        return $this->successResponse($data, 'Monthly statistics retrieved successfully');
    }

    public function getUserAttendanceMonthlyStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2020|max:2100',
        ]);

        $metrics = $this->attendanceService->getUserAttendanceMetrics(
            $request->user(),
            $validated['month'] ?? null,
            $validated['year'] ?? null
        );

        $topAttendees = $this->attendanceService->getTopAttendees(
            $validated['month'] ?? null,
            $validated['year'] ?? null,
        );

        return $this->successResponse(
            [
                'metrics' => $metrics,
                'topAttendees' => $topAttendees
            ],
            'Attendance metrics retrieved successfully'
        );
    }

    public function awardMonthlyBadges(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2020|max:2100',
        ]);

        $metrics = $this->attendanceService->awardMonthlyBadges(
            $validated['year'] ?? null,
            $validated['month'] ?? null,
        );

        return $this->successResponse(
            $metrics,
            'Attendance metrics retrieved successfully'
        );
    }

    public function getAttendanceReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'attendance_date' => 'required|date',
        ]);

        $serviceId = $validated['service_id'];
        $attendanceDate = $validated['attendance_date'];

        $service = Service::select(['id', 'name', 'description', 'day_of_week', 'start_time', 'service_date'])
            ->findOrFail($serviceId);

        $report = [
            'service' => $service,
            'attendance_date' => $attendanceDate,
            'usher_count' => $this->getUsherAttendanceData($attendanceDate),
            'members_statistics' => $this->getMembersStatistics($serviceId, $attendanceDate),
            'members_attendance' => $this->getMembersAttendanceList($serviceId, $attendanceDate),
            'first_timers' => $this->getFirstTimersList($attendanceDate),
        ];

        return response()->json([
            'success' => true,
            'data' => $report
        ], 200);
    }

    private function getUsherAttendanceData(string $attendanceDate): ?array
    {
        $usherData = UsherAttendance::whereDate('service_date', $attendanceDate)
            ->select(['male', 'female', 'children', 'total_attendance', 'service_day', 'service_day_desc'])
            ->first();

        return $usherData ? [
            'male' => $usherData->male,
            'female' => $usherData->female,
            'children' => $usherData->children,
            'total' => $usherData->total_attendance,
            'service_day' => $usherData->service_day,
            'service_description' => $usherData->service_day_desc,
        ] : null;
    }

    private function getMembersStatistics(int $serviceId, string $attendanceDate): array
    {
        $memberRoleIds = $this->getMemberRoleIds();

        $stats = Attendance::where('service_id', $serviceId)
            ->whereDate('attendance_date', $attendanceDate)
            ->whereHas('user.roles', fn($q) => $q->whereIn('roles.id', $memberRoleIds))
            ->selectRaw('
                COUNT(*) as total_marked,
                SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as total_present,
                SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as total_absent,
                SUM(CASE WHEN status = "present" AND mode = "onsite" THEN 1 ELSE 0 END) as onsite_present,
                SUM(CASE WHEN status = "present" AND mode = "online" THEN 1 ELSE 0 END) as online_present
            ')
            ->first();

        $attendanceRate = $stats->total_marked > 0
            ? round(($stats->total_present / $stats->total_marked) * 100, 2)
            : 0;

        return [
            'total_members' => User::members()->count(),
            'total_marked_attendance' => (int) $stats->total_marked,
            'total_present' => (int) $stats->total_present,
            'total_absent' => (int) $stats->total_absent,
            'attendance_rate' => $attendanceRate,
            'mode_breakdown' => [
                'onsite' => (int) $stats->onsite_present,
                'online' => (int) $stats->online_present,
            ],
        ];
    }

    private function getMembersAttendanceList(int $serviceId, string $attendanceDate): array
    {
        $memberRoleIds = $this->getMemberRoleIds();

        $attendances = Attendance::with([
            'user:id,first_name,last_name,email,phone_number,gender,avatar',
            'user.roles:id,name',
        ])
            ->where('service_id', $serviceId)
            ->whereDate('attendance_date', $attendanceDate)
            ->whereHas('user.roles', fn($q) => $q->whereIn('roles.id', $memberRoleIds))
            ->select(['id', 'user_id', 'service_id', 'attendance_date', 'status', 'mode', 'created_at'])
            ->orderByRaw("FIELD(status, 'absent', 'present')")
            ->orderBy('created_at', 'desc')
            ->get();

        if ($attendances->isEmpty()) {
            return [];
        }

        $userIds = $attendances->pluck('user_id')->unique();

        // Bulk fetch absentee assignments
        $absenteeAssignments = AbsenteeAssignment::where('service_id', $serviceId)
            ->whereDate('attendance_date', $attendanceDate)
            ->whereIn('user_id', $userIds)
            ->with('leader:id,first_name,last_name,email,phone_number')
            ->get()
            ->keyBy('user_id');

        // Bulk fetch all feedbacks for these users
        $allFeedbacks = FollowupFeedback::whereIn('user_id', $userIds)
            ->with('createdBy:id,first_name,last_name')
            ->select(['id', 'user_id', 'created_by', 'type', 'note', 'service_date', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('user_id');

        // Bulk fetch service-specific feedbacks (by assigned leader only)
        $serviceFeedbacks = $this->fetchServiceFeedbacksForMembers($absenteeAssignments);

        return $attendances->map(function ($attendance) use ($absenteeAssignments, $serviceFeedbacks, $allFeedbacks) {
            $assignment = $absenteeAssignments->get($attendance->user_id);
            $serviceFeedbacksList = $serviceFeedbacks->get($attendance->user_id, collect());
            $allFeedbacksList = $allFeedbacks->get($attendance->user_id, collect());

            return [
                'attendance_id' => $attendance->id,
                'attendance_status' => $attendance->status,
                'attendance_mode' => $attendance->mode,
                'marked_at' => $attendance->created_at?->format('Y-m-d H:i:s'),
                'user' => [
                    'id' => $attendance->user->id,
                    'name' => $this->formatFullName($attendance->user->first_name, $attendance->user->last_name),
                    'email' => $attendance->user->email,
                    'phone' => $attendance->user->phone_number,
                    'gender' => $attendance->user->gender,
                    'avatar' => $attendance->user->avatar,
                    'roles' => $attendance->user->roles->pluck('name')->toArray(),
                ],
                'absentee_assignment' => $assignment ? [
                    'assignment_id' => $assignment->id,
                    'assigned_leader' => [
                        'id' => $assignment->leader->id,
                        'name' => $this->formatFullName($assignment->leader->first_name, $assignment->leader->last_name),
                        'email' => $assignment->leader->email,
                        'phone' => $assignment->leader->phone_number,
                    ],
                    'assigned_at' => $assignment->created_at?->format('Y-m-d H:i:s'),
                ] : null,
                'service_feedbacks' => $this->formatFeedbacks($serviceFeedbacksList),
                'all_feedbacks' => $this->formatFeedbacks($allFeedbacksList),
            ];
        })->toArray();
    }

    private function getFirstTimersList(string $attendanceDate): array
    {
        $firstTimers = User::with('followUpStatus:id,title,color,description')
            ->whereDate('date_of_visit', $attendanceDate)
            ->select([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone_number',
                'gender',
                'avatar',
                'date_of_visit',
                'followup_by_id',
                'follow_up_status_id',
                'created_at'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        if ($firstTimers->isEmpty()) {
            return [];
        }

        $userIds = $firstTimers->pluck('id');
        $followupByIds = $firstTimers->pluck('followup_by_id')->filter()->unique();

        // Bulk fetch assigned follow-up persons
        $assignedPersons = User::whereIn('id', $followupByIds)
            ->select(['id', 'first_name', 'last_name', 'email', 'phone_number'])
            ->get()
            ->keyBy('id');

        // Bulk fetch all feedbacks
        $allFeedbacks = FollowupFeedback::whereIn('user_id', $userIds)
            ->with('createdBy:id,first_name,last_name')
            ->select(['id', 'user_id', 'created_by', 'type', 'note', 'service_date', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('user_id');

        // Bulk fetch attendance history
        $attendanceHistory = Attendance::whereIn('user_id', $userIds)
            ->with('service:id,name,service_date')
            ->select(['id', 'user_id', 'service_id', 'attendance_date', 'status', 'mode', 'created_at'])
            ->orderBy('attendance_date', 'desc')
            ->get()
            ->groupBy('user_id');

        return $firstTimers->map(function ($firstTimer) use ($assignedPersons, $allFeedbacks, $attendanceHistory) {
            $assignedPerson = $assignedPersons->get($firstTimer->followup_by_id);
            $feedbacks = $allFeedbacks->get($firstTimer->id, collect());
            $history = $attendanceHistory->get($firstTimer->id, collect());

            return [
                'user_id' => $firstTimer->id,
                'date_of_visit' => $firstTimer->date_of_visit?->format('Y-m-d'),
                'registered_at' => $firstTimer->created_at?->format('Y-m-d H:i:s'),
                'user' => [
                    'id' => $firstTimer->id,
                    'name' => $this->formatFullName($firstTimer->first_name, $firstTimer->last_name),
                    'email' => $firstTimer->email,
                    'phone' => $firstTimer->phone_number,
                    'gender' => $firstTimer->gender,
                    'avatar' => $firstTimer->avatar,
                    'follow_up_status' => $firstTimer->followUpStatus ? [
                        'id' => $firstTimer->followUpStatus->id,
                        'title' => $firstTimer->followUpStatus->title,
                        'color' => $firstTimer->followUpStatus->color,
                    ] : null,
                ],
                'assigned_followup_person' => $assignedPerson ? [
                    'id' => $assignedPerson->id,
                    'name' => $this->formatFullName($assignedPerson->first_name, $assignedPerson->last_name),
                    'email' => $assignedPerson->email,
                    'phone' => $assignedPerson->phone_number,
                ] : null,
                'feedbacks' => $this->formatFeedbacks($feedbacks),
                'attendance_history' => $this->formatAttendanceHistory($history),
            ];
        })->toArray();
    }

    // ==================== Helper Methods ====================

    /**
     * Get member role IDs
     */
    private function getMemberRoleIds(): Collection
    {
        return DB::table('roles')
            ->whereIn('name', [
                RoleEnum::PASTOR->value,
                RoleEnum::ADMIN->value,
                RoleEnum::LEADER->value,
                RoleEnum::MEMBER->value,
            ])
            ->pluck('id');
    }

    /**
     * Fetch service-specific feedbacks for members (by assigned leader)
     */
    private function fetchServiceFeedbacksForMembers(Collection $absenteeAssignments): Collection
    {
        if ($absenteeAssignments->isEmpty()) {
            return collect();
        }

        // Build efficient WHERE IN query instead of multiple ORs
        $conditions = $absenteeAssignments->map(fn($assignment) => [
            'user_id' => $assignment->user_id,
            'leader_id' => $assignment->leader_id,
        ])->values();

        // Use whereIn with raw SQL for better performance
        return FollowupFeedback::where(function ($query) use ($conditions) {
            foreach ($conditions as $condition) {
                $query->orWhere(function ($q) use ($condition) {
                    $q->where('user_id', $condition['user_id'])
                        ->where('created_by', $condition['leader_id']);
                });
            }
        })
            ->with('createdBy:id,first_name,last_name')
            ->select(['id', 'user_id', 'created_by', 'type', 'note', 'service_date', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('user_id');
    }

    /**
     * Format full name from first and last name
     */
    private function formatFullName(?string $firstName, ?string $lastName): string
    {
        return trim("{$firstName} {$lastName}");
    }

    /**
     * Format feedback collection
     */
    private function formatFeedbacks(Collection $feedbacks): array
    {
        return $feedbacks->map(function ($feedback) {
            return [
                'id' => $feedback->id,
                'type' => $feedback->type,
                'note' => $feedback->note,
                'service_date' => $feedback->service_date?->format('Y-m-d'),
                'created_at' => $feedback->created_at?->format('Y-m-d H:i:s'),
                'created_by' => $feedback->createdBy ? [
                    'id' => $feedback->createdBy->id,
                    'name' => $this->formatFullName($feedback->createdBy->first_name, $feedback->createdBy->last_name),
                ] : null,
            ];
        })->values()->toArray();
    }

    /**
     * Format attendance history
     */
    private function formatAttendanceHistory(Collection $history): array
    {
        return $history->map(function ($attendance) {
            return [
                'attendance_id' => $attendance->id,
                'service' => [
                    'id' => $attendance->service->id,
                    'name' => $attendance->service->name,
                    'service_date' => $attendance->service->service_date?->format('Y-m-d'),
                ],
                'attendance_date' => $attendance->attendance_date?->format('Y-m-d'),
                'status' => $attendance->status,
                'mode' => $attendance->mode,
                'marked_at' => $attendance->created_at?->format('Y-m-d H:i:s'),
            ];
        })->values()->toArray();
    }
}
