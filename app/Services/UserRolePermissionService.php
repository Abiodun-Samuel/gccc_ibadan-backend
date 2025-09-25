<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserRolePermissionService
{
    public function assignRoleAndSyncPermissions(User $user, array|string $roles): User
    {
        $roles = array_unique(array_merge($roles, [RoleEnum::MEMBER->value]));
        $user->assignRole($roles);

        $rolePermissions = Role::whereIn('name', $roles)
            ->with('permissions')
            ->get()
            ->flatMap->permissions
            ->pluck('name')
            ->unique()
            ->toArray();

        // Sync permissions dynamically
        $user->syncPermissions($rolePermissions);

        return $user->load(['roles', 'permissions']);
    }

    public function removeRoleAndPermissions(User $user, string $role): User
    {
        // Prevent removing base MEMBER role
        if ($role === RoleEnum::MEMBER->value) {
            return $user->load(['roles', 'permissions']);
        }

        $user->removeRole($role);
        $rolePermissions = Role::where('name', $role)
            ->with('permissions')
            ->first()?->permissions
            ->pluck('name')
            ->toArray() ?? [];

        if (!empty($rolePermissions)) {
            $user->revokePermissionTo($rolePermissions);
        }

        if (!$user->hasRole(RoleEnum::MEMBER->value)) {
            $user->assignRole(RoleEnum::MEMBER->value);
        }

        return $user->load(['roles', 'permissions']);
    }

    public function syncPermissions(User $user, array $permissions): User
    {
        $user->syncPermissions($permissions);

        return $user->load('permissions');
    }

    public function createPermission(string $name, ?string $guardName = null): Permission
    {
        return Permission::create([
            'name' => $name,
            'guard_name' => $guardName ?? config('auth.defaults.guard'),
        ]);
    }

    public function updatePermission(Permission $permission, string $newName): Permission
    {
        $permission->update(['name' => $newName]);
        return $permission;
    }

    public function deletePermission(Permission $permission): void
    {
        $permission->delete();
    }

    public function listPermissions(): Collection
    {
        return Permission::all();
    }
}
