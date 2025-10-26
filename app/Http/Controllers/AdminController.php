<?php

namespace App\Http\Controllers;

use App\Services\AdminService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


class AdminController extends Controller
{
    public $adminService;
    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }
    // public function assignRoles(User $user, Request $request)
    // {
    //     $validated = $request->validate([
    //         'roles' => ['array', 'min:1'],
    //         'roles.*' => ['string', 'in:admin,leader,member'],
    //     ]);

    //     $this->service->assignRoleAndSyncPermissions($user, $validated['roles']);

    //     return response()->json([
    //         'user' => $user->fresh()->only(['id', 'name']),
    //         'roles' => $user->getRoleNames(),
    //     ]);
    // }
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
