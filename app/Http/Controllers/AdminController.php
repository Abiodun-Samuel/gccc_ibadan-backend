<?php

namespace App\Http\Controllers;

use App\Services\AdminService;
use App\Services\UserRoleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


class AdminController extends Controller
{
    public $adminService;
    public $userRoleService;
    public function __construct(AdminService $adminService, UserRoleService $userRoleService)
    {
        $this->adminService = $adminService;
        $this->userRoleService = $userRoleService;
    }
    public function assignRoleToUsers(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'role' => 'required|in:admin,leader,member',
        ]);
        $this->userRoleService->assignRoleToUsers(
            $validated['user_ids'],
            $validated['role']
        );
        return $this->successResponse('', 'Role assignement was successful', Response::HTTP_OK);
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
