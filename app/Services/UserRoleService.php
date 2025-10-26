<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\User;

class UserRoleService
{
    public function assignUserRoles(User $user, array|string $roles): User
    {
        $roles = is_string($roles) ? [$roles] : $roles;
        $hierarchicalRoles = $this->resolveHierarchicalRoles($roles);
        $user->assignRole($hierarchicalRoles);

        return $user->load(['roles']);
    }

    public function updateUserRole(User $user, string $newRole): User
    {
        $user->syncRoles([]);
        return $this->assignUserRoles($user, [$newRole]);
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


    public function removeRole(User $user, string $role): User
    {

        if ($role === RoleEnum::MEMBER->value) {
            return $user->load(['roles']);
        }

        $user->removeRole($role);

        if (!$user->hasRole(RoleEnum::MEMBER->value)) {
            $user->assignRole(RoleEnum::MEMBER->value);
        }

        return $user->load(['roles']);
    }

}
