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
        $roles = is_string($roles) ? [$roles] : $roles;

        // Get the hierarchical roles based on the highest role
        $hierarchicalRoles = $this->resolveHierarchicalRoles($roles);

        // Assign roles to user
        $user->assignRole($hierarchicalRoles);

        // Get all permissions from assigned roles
        $rolePermissions = Role::whereIn('name', $hierarchicalRoles)
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

    public function updateUserRole(User $user, string $newRole): User
    {
        $user->syncRoles([]);
        return $this->assignRoleAndSyncPermissions($user, [$newRole]);
    }


    private function resolveHierarchicalRoles(array $roles): array
    {
        $uniqueRoles = array_unique($roles);
        if (in_array(RoleEnum::ADMIN->value, $uniqueRoles)) {
            return [
                RoleEnum::ADMIN->value,
                RoleEnum::LEADER->value,
                RoleEnum::MEMBER->value,
            ];
        }
        if (in_array(RoleEnum::LEADER->value, $uniqueRoles)) {
            return [
                RoleEnum::LEADER->value,
                RoleEnum::MEMBER->value,
            ];
        }
        return [RoleEnum::MEMBER->value];
    }

    public function hasHierarchicalRole(User $user, string $role): bool
    {
        return match ($role) {
            RoleEnum::ADMIN->value => $user->hasRole(RoleEnum::ADMIN->value),
            RoleEnum::LEADER->value => $user->hasAnyRole([
                RoleEnum::ADMIN->value,
                RoleEnum::LEADER->value
            ]),
            RoleEnum::MEMBER->value => $user->hasAnyRole([
                RoleEnum::ADMIN->value,
                RoleEnum::LEADER->value,
                RoleEnum::MEMBER->value
            ]),
            default => false,
        };
    }

    public function getPrimaryRole(User $user): ?string
    {
        if ($user->hasRole(RoleEnum::ADMIN->value)) {
            return RoleEnum::ADMIN->value;
        }

        if ($user->hasRole(RoleEnum::LEADER->value)) {
            return RoleEnum::LEADER->value;
        }

        if ($user->hasRole(RoleEnum::MEMBER->value)) {
            return RoleEnum::MEMBER->value;
        }

        return null;
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
