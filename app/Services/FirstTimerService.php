<?php

namespace App\Services;

use App\Enums\FollowUpStatusEnum;
use App\Enums\RoleEnum;
use App\Enums\UnitEnum;
use App\Models\FollowUpStatus;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FirstTimerService
{
    public function __construct(
        private readonly UploadService $uploadService,
        private readonly MailService $mailService
    ) {}

    /**
     * Apply filters to attendance query
     */
    private function applyAttendanceFilters($query, array $filters): void
    {
        $query->when(
            !empty($filters['assigned_to_member']),
            fn($q) => $q->where('followup_by_id', $filters['assigned_to_member'])
        );

        $query->when(
            !empty($filters['follow_up_status']),
            fn($q) => $q->where('follow_up_status_id', $filters['follow_up_status'])
        );

        $query->when(
            !empty($filters['date_of_visit']),
            fn($q) => $q->whereDate('date_of_visit', $filters['date_of_visit'])
        );

        $query->when(
            !empty($filters['date_month_of_visit']),
            fn($q) => $q->whereMonth('date_of_visit', $filters['date_month_of_visit'])
        );

        $query->when(
            !empty($filters['week_ending']),
            fn($q) => $q->whereDate('week_ending', $filters['week_ending'])
        );
    }

    /**
     * Get all first timers with optional filters
     */
    public function getAllFirstTimers(array $filters = []): Collection
    {
        $query = User::firstTimers()
            ->with(['followUpStatus', 'assignedTo', 'attendances.service', 'followupFeedbacks']);
        $this->applyAttendanceFilters($query, $filters);
        return $query
            ->orderBy('date_of_visit', 'desc')
            ->get();
    }

    /**
     * Create a new first timer
     */
    public function createFirstTimer($data): User
    {
        return DB::transaction(function () use ($data) {
            $followupMember = $this->findLeastLoadedFollowupMember($data['gender']);

            $firstTimer = User::create(array_merge($data, [
                'followup_by_id' => $followupMember?->id,
                'follow_up_status_id' => FollowUpStatus::NOT_CONTACTED_ID,
                'assigned_at' => $followupMember != null ? now() : null,
                'week_ending' => getNextSunday()?->toDateString(),
                'password' => Hash::make($data['phone_number'])
            ]));

            $recipients = [['name' => $followupMember?->first_name, 'email' => $followupMember?->email]];

            $emailData = [
                "first_timer_name" => $firstTimer->first_name ?? '',
                "first_timer_email" => $firstTimer?->email ?? '',
                "first_timer_phone" => $firstTimer?->phone_number ?? ''
            ];

            $this->mailService->sendFirstTimerAssignedEmail($recipients, [], [], $emailData);

            $firstTimer->assignRole(RoleEnum::FIRST_TIMER->value);
            $firstTimer->load(['followUpStatus', 'assignedTo']);

            return $firstTimer;
        });
    }

    /**
     * Get a first timer by ID with relationships
     */
    public function getFirstTimerById(User $firstTimer): User
    {
        if (!$firstTimer->hasRole(RoleEnum::FIRST_TIMER->value)) {
            throw new NotFoundHttpException('The first timer you are looking for may have been removed or no longer exists.');
        }
        return $firstTimer->load(['followUpStatus', 'assignedTo']);
    }

    /**
     * Update first timer details
     */
    public function updateFirstTimer(User $firstTimer, array $data): User
    {
        return DB::transaction(function () use ($firstTimer, $data) {
            $oldFollowUpStatusId = $firstTimer->follow_up_status_id;

            $firstTimer->update($data);

            $newFollowUpStatusId = $data['follow_up_status_id'] ?? null;

            // Auto-assign member role when status changes to integrated (ID: 7)
            if ($newFollowUpStatusId == 7 && $oldFollowUpStatusId != 7) {
                $this->assignMemberRole($firstTimer);
            }

            return $firstTimer->fresh(['followUpStatus', 'assignedTo']);
        });
    }

    /**
     * Assign member role to first timer
     */
    private function assignMemberRole(User $firstTimer): void
    {
        if (!$firstTimer->hasRole(RoleEnum::MEMBER->value)) {
            $firstTimer->assignRole(RoleEnum::MEMBER->value);
        }
    }

    /**
     * Handle avatar upload for first timer
     */
    public function handleAvatarUpload(User $firstTimer, $secondary_avatar, string $folder): string
    {
        if ($firstTimer->secondary_avatar) {
            $this->uploadService->delete($firstTimer->secondary_avatar);
        }

        return $this->uploadService->upload($secondary_avatar, $folder);
    }

    /**
     * Get first timers assigned to a specific member
     */
    public function getAssignedFirstTimers(User $member): Collection
    {
        return $member->assignedUsers()
            ->firstTimers()
            ->with(['followUpStatus', 'assignedTo'])
            ->latest('date_of_visit')
            ->get();
    }

    /**
     * Send welcome email and update status
     */
    public function sendWelcomeEmail(User $firstTimer, string $email, string $name): User
    {
        return DB::transaction(function () use ($firstTimer, $email, $name) {
            $updatedFirstTimer = $this->updateFirstTimer(
                $firstTimer,
                ['follow_up_status_id' => FollowUpStatus::CONTACTED_ID]
            );

            $recipients = [
                [
                    'name' => $name,
                    'email' => $email
                ]
            ];

            $this->mailService->sendFirstTimerWelcomeEmail($recipients);

            return $updatedFirstTimer;
        });
    }

    /**
     * Get first timers analytics for a specific year
     */
    public function getFirstTimersAnalytics(int $year): array
    {
        $monthNames = $this->getMonthNames();
        $statuses = FollowUpStatus::pluck('title', 'id');
        $integratedStatusId = FollowUpStatus::where('title', FollowUpStatusEnum::INTEGRATED->value)->value('id');

        $results = $this->fetchAnalyticsData($year, $integratedStatusId);

        return $this->processAnalyticsData($results, $monthNames, $statuses, $integratedStatusId, $year);
    }

    /**
     * Find the follow-up member with the least assignments
     */
    public function findLeastLoadedFollowupMember(?string $gender = null): ?User
    {
        $followupUnitId = Unit::where('name', UnitEnum::FOLLOW_UP->value)->value('id');
        if (!$followupUnitId) {
            return null;
        }

        return User::query()
            ->whereHas('units', fn($q) => $q->where('units.id', $followupUnitId))
            ->when($gender, fn($q) => $q->where('gender', $gender))
            ->withCount(['assignedUsers as assigned_first_timers_count' => fn($q) => $q->activelyFollowedFirstTimers()])
            ->orderBy('assigned_first_timers_count', 'asc')
            ->orderBy('id', 'asc')
            ->first();
    }

    /**
     * Get month names array
     */
    private function getMonthNames(): array
    {
        return [
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
            12 => 'December',
        ];
    }

    /**
     * Fetch analytics data from database
     */
    private function fetchAnalyticsData(int $year, ?int $integratedStatusId): Collection
    {
        return User::firstTimers()
            ->selectRaw('
                MONTH(date_of_visit) as month,
                follow_up_status_id,
                COUNT(*) as total,
                SUM(CASE WHEN follow_up_status_id = ? THEN 1 ELSE 0 END) as integrated_count
            ', [$integratedStatusId])
            ->whereYear('date_of_visit', $year)
            ->groupBy(DB::raw('MONTH(date_of_visit)'), 'follow_up_status_id')
            ->get();
    }

    /**
     * Process raw analytics data into structured format
     */
    private function processAnalyticsData(
        Collection $results,
        array $monthNames,
        Collection $statuses,
        ?int $integratedStatusId,
        int $year
    ): array {
        $statusPerMonth = $this->initializeStatusPerMonth($monthNames, $statuses);
        $monthlyTotals = array_fill(1, 12, 0);
        $monthlyIntegrated = array_fill(1, 12, 0);

        foreach ($results as $row) {
            $month = (int) $row->month;

            $monthlyTotals[$month] += $row->total;

            if ($row->follow_up_status_id == $integratedStatusId) {
                $monthlyIntegrated[$month] += $row->total;
            }

            $statusTitle = $statuses[$row->follow_up_status_id] ?? 'Unknown';
            $statusPerMonth[$month][$statusTitle] = (int) $row->total;
        }

        return [
            'year' => $year,
            'statusesPerMonth' => array_values($statusPerMonth),
            'totalFirstTimers' => $this->formatMonthlyData($monthNames, $monthlyTotals),
            'integratedFirstTimers' => $this->formatMonthlyData($monthNames, $monthlyIntegrated),
        ];
    }

    /**
     * Initialize status per month structure
     */
    private function initializeStatusPerMonth(array $monthNames, Collection $statuses): array
    {
        $statusPerMonth = [];

        foreach (range(1, 12) as $month) {
            $statusRow = ['month' => $monthNames[$month]];

            foreach ($statuses as $statusTitle) {
                $statusRow[$statusTitle] = 0;
            }

            $statusPerMonth[$month] = $statusRow;
        }

        return $statusPerMonth;
    }

    /**
     * Format monthly data for response
     */
    private function formatMonthlyData(array $monthNames, array $monthlyCounts): array
    {
        $formatted = [];

        foreach (range(1, 12) as $month) {
            $formatted[] = [
                'month' => $monthNames[$month],
                'count' => $monthlyCounts[$month],
            ];
        }

        return $formatted;
    }

    public function getFirstTimerReport(int $year): array
    {
        $monthNames = $this->getMonthNames();

        // Single query with eager loading
        $statuses = FollowUpStatus::pluck('title', 'id');
        $integratedStatusId = FollowUpStatus::where('title', FollowUpStatusEnum::INTEGRATED->value)->value('id');

        // Get all first timers with feedbacks in one optimized query
        $firstTimers = User::firstTimers()
            ->select('id', 'first_name', 'last_name', 'phone_number', 'email', 'follow_up_status_id', 'date_of_visit', 'gender')
            ->with([
                'followUpFeedbacks' => function ($query) {
                    $query->select('id', 'user_id', 'created_by', 'note', 'type', 'service_date', 'created_at', 'updated_at')
                        ->latest()
                        ->with(['createdBy:id,first_name,last_name,email,avatar,phone_number,gender']);
                }
            ])
            ->whereYear('date_of_visit', $year)
            ->orderBy('date_of_visit', 'desc')
            ->get();

        // Early return if no data
        if ($firstTimers->isEmpty()) {
            return $this->getEmptyReport($year, $monthNames, $statuses);
        }

        // Calculate all metrics in a single pass
        $metrics = $this->calculateMetrics($firstTimers, $statuses, $integratedStatusId, $monthNames);

        return [
            'year' => $year,
            'summary' => [
                'total_first_timers' => $metrics['total_count'],
                'total_integrated' => $metrics['integrated_count'],
                'integration_rate' => $metrics['total_count'] > 0
                    ? round(($metrics['integrated_count'] / $metrics['total_count']) * 100, 2)
                    : 0,
                'total_male' => $metrics['male_count'],
                'total_female' => $metrics['female_count'],
                'average_per_month' => round($metrics['total_count'] / 12, 2),
                'peak_month' => $metrics['peak_month'],
            ],
            'monthly_breakdown' => $metrics['monthly_data'],
            'status_summary' => $metrics['status_summary'],
        ];
    }

    /**
     * Calculate all metrics in a single iteration
     */
    private function calculateMetrics(Collection $firstTimers, Collection $statuses, ?int $integratedStatusId, array $monthNames): array
    {
        // Initialize counters
        $totalCount = 0;
        $integratedCount = 0;
        $maleCount = 0;
        $femaleCount = 0;
        $monthlyData = $this->initializeMonthlyData($monthNames, $statuses);
        $statusGroups = [];
        $monthlyCounts = array_fill(1, 12, 0);

        // Single pass through all first timers
        foreach ($firstTimers as $firstTimer) {
            $totalCount++;
            $month = (int) date('n', strtotime($firstTimer->date_of_visit));
            $statusId = $firstTimer->follow_up_status_id;
            $statusTitle = $statuses->get($statusId, 'Unknown');

            // Format followup feedbacks
            $feedbacks = $firstTimer->followUpFeedbacks->map(function ($feedback) {
                return [
                    'id' => $feedback->id,
                    'type' => $feedback->type,
                    'note' => $feedback->note,
                    'service_date' => $feedback->service_date?->format('Y-m-d'),
                    'service_date_human' => $feedback->service_date?->format('M d, Y'),
                    'created_by' => $feedback->createdBy ? [
                        'id' => $feedback->createdBy->id,
                        'full_name' => trim($feedback->createdBy->first_name . ' ' . $feedback->createdBy->last_name),
                        'initials' => generateInitials($feedback->createdBy->first_name, $feedback->createdBy->last_name),
                        'email' => $feedback->createdBy->email,
                        'avatar' => $feedback->createdBy->avatar,
                        'phone' => $feedback->createdBy->phone_number,
                        'gender' => $feedback->createdBy->gender,
                    ] : null,
                    'created_at' => $feedback->created_at->toIso8601String(),
                    'created_at_human' => $feedback->created_at->diffForHumans(),
                    'updated_at' => $feedback->updated_at->toIso8601String(),
                    'updated_at_human' => $feedback->updated_at->diffForHumans(),
                ];
            })->toArray();

            // Prepare first timer data once
            $firstTimerData = [
                'id' => $firstTimer->id,
                'first_name' => $firstTimer->first_name,
                'last_name' => $firstTimer->last_name,
                'full_name' => trim($firstTimer->first_name . ' ' . $firstTimer->last_name),
                'phone_number' => $firstTimer->phone_number,
                'email' => $firstTimer->email,
                'gender' => $firstTimer->gender,
                'date_of_visit' => $firstTimer->date_of_visit,
                'status' => $statusTitle,
                'followup_feedbacks' => $feedbacks,
                'feedbacks_count' => count($feedbacks),
            ];

            // Update monthly data
            $monthlyData[$month]['total_count']++;
            $monthlyData[$month]['first_timers'][] = $firstTimerData;
            $monthlyCounts[$month]++;

            // Count gender
            $gender = strtolower($firstTimer->gender ?? '');
            if ($gender === 'male') {
                $maleCount++;
                $monthlyData[$month]['male_count']++;
            } elseif ($gender === 'female') {
                $femaleCount++;
                $monthlyData[$month]['female_count']++;
            }

            // Count integrated
            if ($statusId == $integratedStatusId) {
                $integratedCount++;
                $monthlyData[$month]['integrated_count']++;
            }

            // Group by status
            if (!isset($monthlyData[$month]['statuses'][$statusTitle])) {
                $monthlyData[$month]['statuses'][$statusTitle] = [
                    'count' => 0,
                    'first_timers' => []
                ];
            }
            $monthlyData[$month]['statuses'][$statusTitle]['count']++;
            $monthlyData[$month]['statuses'][$statusTitle]['first_timers'][] = $firstTimerData;

            // Group for status summary
            if (!isset($statusGroups[$statusTitle])) {
                $statusGroups[$statusTitle] = [
                    'status' => $statusTitle,
                    'status_id' => $statusId !== null && $statuses->has($statusId) ? $statusId : null,
                    'count' => 0,
                    'first_timers' => []
                ];
            }
            $statusGroups[$statusTitle]['count']++;
            $statusGroups[$statusTitle]['first_timers'][] = [
                'id' => $firstTimer->id,
                'first_name' => $firstTimer->first_name,
                'last_name' => $firstTimer->last_name,
                'full_name' => trim($firstTimer->first_name . ' ' . $firstTimer->last_name),
                'phone_number' => $firstTimer->phone_number,
                'email' => $firstTimer->email,
                'gender' => $firstTimer->gender,
                'date_of_visit' => $firstTimer->date_of_visit,
                'followup_feedbacks' => $feedbacks,
                'feedbacks_count' => count($feedbacks),
            ];
        }

        // Calculate percentages for status summary
        $statusSummary = [];
        foreach ($statusGroups as $statusGroup) {
            $statusSummary[] = array_merge($statusGroup, [
                'percentage' => $totalCount > 0
                    ? round(($statusGroup['count'] / $totalCount) * 100, 2)
                    : 0
            ]);
        }

        // Find peak month
        $maxCount = max($monthlyCounts);
        $peakMonth = array_search($maxCount, $monthlyCounts);

        return [
            'total_count' => $totalCount,
            'integrated_count' => $integratedCount,
            'male_count' => $maleCount,
            'female_count' => $femaleCount,
            'monthly_data' => array_values($monthlyData),
            'status_summary' => $statusSummary,
            'peak_month' => [
                'month' => $monthNames[$peakMonth],
                'count' => $maxCount
            ]
        ];
    }

    /**
     * Initialize monthly data structure
     */
    private function initializeMonthlyData(array $monthNames, Collection $statuses): array
    {
        $monthlyData = [];

        foreach (range(1, 12) as $month) {
            $monthlyData[$month] = [
                'month' => $monthNames[$month],
                'month_number' => $month,
                'total_count' => 0,
                'integrated_count' => 0,
                'male_count' => 0,
                'female_count' => 0,
                'statuses' => [],
                'first_timers' => []
            ];

            // Pre-initialize known statuses only
            foreach ($statuses as $statusTitle) {
                $monthlyData[$month]['statuses'][$statusTitle] = [
                    'count' => 0,
                    'first_timers' => []
                ];
            }
        }

        return $monthlyData;
    }

    /**
     * Get empty report structure
     */
    private function getEmptyReport(int $year, array $monthNames, Collection $statuses): array
    {
        $emptyMonthly = [];
        foreach (range(1, 12) as $month) {
            $statusData = [];
            foreach ($statuses as $statusTitle) {
                $statusData[$statusTitle] = ['count' => 0, 'first_timers' => []];
            }

            $emptyMonthly[] = [
                'month' => $monthNames[$month],
                'month_number' => $month,
                'total_count' => 0,
                'integrated_count' => 0,
                'male_count' => 0,
                'female_count' => 0,
                'statuses' => $statusData,
                'first_timers' => []
            ];
        }

        return [
            'year' => $year,
            'summary' => [
                'total_first_timers' => 0,
                'total_integrated' => 0,
                'integration_rate' => 0,
                'total_male' => 0,
                'total_female' => 0,
                'average_per_month' => 0,
                'peak_month' => ['month' => $monthNames[1], 'count' => 0],
            ],
            'monthly_breakdown' => $emptyMonthly,
            'status_summary' => [],
        ];
    }
}
