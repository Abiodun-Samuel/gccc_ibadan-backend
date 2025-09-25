<?php
namespace App\Http\Controllers;

use App\Services\UserRolePermissionService;
use App\Models\User;
use Illuminate\Http\Client\Request;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly UserRolePermissionService $service
    ) {
    }

    public function assignRoles(User $user, Request $request)
    {
        $validated = $request->validate([
            'roles' => ['array', 'min:1'],
            'roles.*' => ['string', 'in:admin,leader,member'],
        ]);

        $this->service->assignRoleAndSyncPermissions($user, $validated['roles']);

        return response()->json([
            'user' => $user->fresh()->only(['id', 'name']),
            'roles' => $user->getRoleNames(),
        ]);
    }

    public function assignPermissions(User $user, Request $request)
    {
        $validated = $request->validate([
            'permissions' => ['array', 'min:1'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->service->syncPermissions($user, $validated['permissions']);

        return response()->json([
            'user' => $user->fresh()->only(['id', 'name']),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }
}
