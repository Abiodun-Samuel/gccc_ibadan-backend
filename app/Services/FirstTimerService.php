<?php

namespace App\Services;

use App\Enums\FollowUpStatusEnum;
use App\Enums\RoleEnum;
use App\Enums\UnitEnum;
use App\Models\FollowUpStatus;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FirstTimerService
{
    public function __construct(
        private readonly UploadService $uploadService,
        private readonly MailService $mailService
    ) {
    }

    /**
     * Get all first timers with relationships
     */
    public function getAllFirstTimers(): Collection
    {
        return User::firstTimers()
            ->with(['followUpStatus', 'assignedTo'])
            ->orderBy('date_of_visit', 'desc')
            ->get();
    }

    /**
     * Create a new first timer
     */
    public function createFirstTimer(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $followupMember = $this->findLeastLoadedFollowupMember($data['gender'] ?? null);

            $firstTimer = User::create(array_merge($data, [
                'followup_by_id' => $followupMember?->id,
                'follow_up_status_id' => FollowUpStatus::NOT_CONTACTED_ID,
                'assigned_at' => $followupMember != null ? now(): null,
                'week_ending' => getNextSunday()?->toDateString(),
                'password' => Hash::make($data['phone_number'])
            ]));

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
        return $firstTimer->load(['followUpStatus', 'assignedTo']);
    }

    /**
     * Update first timer details
     */
    public function updateFirstTimer(User $firstTimer, array $data): User
    {
        return DB::transaction(function () use ($firstTimer, $data) {
            $firstTimer->update($data);
            return $firstTimer->fresh(['followUpStatus', 'assignedTo']);
        });
    }

    /**
     * Handle avatar upload for first timer
     */
    public function handleAvatarUpload(User $firstTimer, $avatar, string $folder): string
    {
        if ($firstTimer->avatar) {
            $this->uploadService->delete($firstTimer->avatar);
        }

        return $this->uploadService->upload($avatar, $folder);
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
        $cacheKey = "first_timers_analytics_{$year}";

        return Cache::remember($cacheKey, now()->addDay(), function () use ($year) {
            $monthNames = $this->getMonthNames();
            $statuses = FollowUpStatus::pluck('title', 'id');
            $integratedStatusId = FollowUpStatus::where('title', FollowUpStatusEnum::INTEGRATED->value)->value('id');

            $results = $this->fetchAnalyticsData($year, $integratedStatusId);

            return $this->processAnalyticsData($results, $monthNames, $statuses, $integratedStatusId, $year);
        });
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
            ->withCount([
                'assignedUsers as assigned_first_timers_count' => fn($q) => $q->firstTimers()
            ])
            ->orderBy('assigned_first_timers_count')
            ->orderBy('id')
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
}
