<?php

namespace App\Http\Controllers;

use App\Enums\FollowUpStatusEnum;
use App\Enums\RoleEnum;
use App\Enums\UnitRoleEnum;
use App\Models\FollowUpStatus;
use App\Models\Unit;
use App\Models\User;
use App\Services\AdminService;
use App\Services\UnitMemberService;
use App\Services\UserRolePermissionService;
use Cache;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;


class AdminController extends Controller
{
    public $adminService;
    public $userRolePermissionService;
    public $unitMemberService;

    public function __construct(AdminService $adminService, UserRolePermissionService $userRolePermissionService, UnitMemberService $unitMemberService)
    {
        $this->adminService = $adminService;
        $this->userRolePermissionService = $userRolePermissionService;
        $this->unitMemberService = $unitMemberService;
    }

    public function getAdminAnalytics(Request $request)
    {

        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))
            : now()->startOfDay();

        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))
            : now()->firstOfMonth();

        $stats = $this->adminService->getAdminAnalytics($startDate, $endDate);

        return $this->successResponse($stats, '', Response::HTTP_OK);
    }

    public function getFirstTimersAnalytics(Request $request)
    {
        $year = (int) $request->query('year', now()->year);

        // Unique cache key per year
        $cacheKey = "first_timers_analytics_{$year}";

        $data = Cache::remember($cacheKey, now()->addDay(), function () use ($year) {
            // Predefine month names
            $monthNames = [
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

            // Fetch all statuses once (id => title)
            $statuses = FollowUpStatus::pluck('title', 'id');

            // Fetch the ID for "Integrated" status
            $integratedStatusId = FollowUpStatus::where('title', FollowUpStatusEnum::INTEGRATED->value)->value('id');

            /**
             * Optimized single query:
             * - Get total counts
             * - Get integrated counts
             * - Group by month + follow_up_status_id
             */
            $results = DB::table('first_timers')
                ->selectRaw('
                MONTH(date_of_visit) as month,
                follow_up_status_id,
                COUNT(*) as total,
                SUM(CASE WHEN follow_up_status_id = ? THEN 1 ELSE 0 END) as integrated_count
            ', [$integratedStatusId])
                ->whereYear('date_of_visit', $year)
                ->groupBy(DB::raw('MONTH(date_of_visit), follow_up_status_id'))
                ->get();

            /**
             * Initialize data structures
             */
            $statusPerMonth = [];
            $monthlyTotals = array_fill(1, 12, 0);
            $monthlyIntegrated = array_fill(1, 12, 0);

            // Initialize rows for each month
            foreach (range(1, 12) as $month) {
                $statusRow = ['month' => $monthNames[$month]];
                foreach ($statuses as $statusTitle) {
                    $statusRow[$statusTitle] = 0;
                }
                $statusPerMonth[$month] = $statusRow;
            }

            /**
             * Fill structures with DB data
             */
            foreach ($results as $row) {
                $month = (int) $row->month;

                // Update totals
                $monthlyTotals[$month] += $row->total;

                // Update integrated totals
                if ($row->follow_up_status_id == $integratedStatusId) {
                    $monthlyIntegrated[$month] += $row->total;
                }

                // Update per-status count
                $statusTitle = $statuses[$row->follow_up_status_id] ?? 'Unknown';
                $statusPerMonth[$month][$statusTitle] = (int) $row->total;
            }

            $totalFirstTimers = [];
            $integratedFirstTimers = [];

            foreach (range(1, 12) as $month) {
                $totalFirstTimers[] = [
                    'month' => $monthNames[$month],
                    'count' => $monthlyTotals[$month],
                ];

                $integratedFirstTimers[] = [
                    'month' => $monthNames[$month],
                    'count' => $monthlyIntegrated[$month],
                ];
            }

            return [
                'year' => $year,
                'statusesPerMonth' => array_values($statusPerMonth),
                'totalFirstTimers' => $totalFirstTimers,
                'integratedFirstTimers' => $integratedFirstTimers,
            ];
        });

        return $this->successResponse($data, 'First timers analytics retrieved successfully', Response::HTTP_OK);
    }

    public function assignMemberToUnit(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['exists:users,id'],
        ]);
        $unit = Unit::findOrFail($validated['unit_id']);
        $this->unitMemberService->assignMembers($unit, $validated['user_ids']);

        return $this->successResponse('', 'Member assigned successfully.', Response::HTTP_OK);
    }

    public function unassignMemberFromUnit(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['exists:users,id'],
        ]);
        $unit = Unit::findOrFail($validated['unit_id']);
        $this->unitMemberService->unassignMembers($unit, $validated['user_ids']);

        return $this->successResponse([], 'Member unassigned successfully.', Response::HTTP_OK);
    }

    public function assignLeaderOrAssistantToUnit(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'role_type' => ['required', Rule::in([UnitRoleEnum::LEADER->value, UnitRoleEnum::ASSISTANT->value])],
        ]);

        $unit = Unit::findOrFail($validated['unit_id']);
        $user = User::findOrFail($validated['user_id']);

        $roleType = UnitRoleEnum::from($validated['role_type']);
        match ($roleType) {
            UnitRoleEnum::LEADER => $unit->update(['leader_id' => $user->id]),
            UnitRoleEnum::ASSISTANT => $unit->update(['assistant_leader_id' => $user->id]),
        };
        $this->userRolePermissionService->assignRoleAndSyncPermissions($user, [RoleEnum::LEADER->value]);

        $message = ucfirst($validated['role_type']) . " assigned successfully.";
        return $this->successResponse($message, 'message', Response::HTTP_OK);

    }
    public function unassignLeaderOrAssistantFromUnit(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'role_type' => ['required', Rule::in([UnitRoleEnum::LEADER->value, UnitRoleEnum::ASSISTANT->value])],
        ]);

        $unit = Unit::findOrFail($validated['unit_id']);
        $user = User::findOrFail($validated['user_id']);

        $roleType = UnitRoleEnum::from($validated['role_type']);

        match ($roleType) {
            UnitRoleEnum::LEADER => $unit->leader_id === $user->id ? $unit->update(['leader_id' => null]) : null,
            UnitRoleEnum::ASSISTANT => $unit->assistant_leader_id === $user->id ? $unit->update(['assistant_leader_id' => null]) : null,
        };

        $stillLeading = Unit::where('leader_id', $user->id)->orWhere('assistant_leader_id', $user->id)->exists();

        if (!$stillLeading && $user->hasRole(RoleEnum::LEADER->value)) {
            $this->userRolePermissionService->removeRoleAndPermissions($user, RoleEnum::LEADER->value);
        }

        $message = ucfirst($validated['role_type']) . " unassigned successfully.";
        return $this->successResponse([], $message, Response::HTTP_OK);
    }
}
