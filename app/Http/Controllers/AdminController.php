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
    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
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
}
