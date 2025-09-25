<?php

namespace App\Http\Controllers;

use App\Services\UserRolePermissionService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct(
        private readonly UserRolePermissionService $service
    ) {
    }

    public function index()
    {
        return $this->service->listPermissions();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:permissions,name',
        ]);

        return $this->service->createPermission($validated['name']);
    }

    public function update(Permission $permission, Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:permissions,name,' . $permission->id,
        ]);

        return $this->service->updatePermission($permission, $validated['name']);
    }

    public function destroy(Permission $permission)
    {
        $this->service->deletePermission($permission);
        return response()->noContent();
    }
}
