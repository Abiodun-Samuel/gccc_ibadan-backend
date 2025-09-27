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

    public function unassignLeaderOrAssistantFromUnit(Request $request)
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

    public function getAttendanceAnalytics(Request $request)
    {

    }
}
