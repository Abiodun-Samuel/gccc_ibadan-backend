<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UserRoleService
{
    public function assignUserRoles(User $user, array|string $roles): User
    {
        $roles = is_string($roles) ? [$roles] : $roles;
        $hierarchicalRoles = $this->resolveHierarchicalRoles($roles);
        $user->assignRole($hierarchicalRoles);

        return $user->load('permissions', 'roles');
    }

    public function assignRoleToUsers(array|Collection $users, string $role): Collection
    {
        $users = $this->normalizeUsers($users);

        return DB::transaction(function () use ($users, $role) {
            $hierarchicalRoles = $this->resolveHierarchicalRoles([$role]);

            foreach ($users as $user) {
                $user->syncRoles($hierarchicalRoles);
            }

            return $users->load('permissions', 'roles');
        });
    }

    public function syncUsersPermissions(array|Collection $users, array $permissions): Collection
    {
        $users = $this->normalizeUsers($users);

        return DB::transaction(function () use ($users, $permissions) {

            foreach ($users as $user) {
                $user->syncPermissions($permissions);
            }

            return  $users->load('permissions', 'roles');
        });
    }

    private function resolveHierarchicalRoles(array $roles): array
    {
        $uniqueRoles = array_unique($roles);
        if (in_array(RoleEnum::PASTOR->value, $uniqueRoles)) {
            return [
                RoleEnum::PASTOR->value,
                RoleEnum::ADMIN->value,
                RoleEnum::LEADER->value,
                RoleEnum::MEMBER->value,
            ];
        }
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
        if (in_array(RoleEnum::FIRST_TIMER->value, $uniqueRoles)) {
            return [
                RoleEnum::FIRST_TIMER->value,
            ];
        }
        return [RoleEnum::MEMBER->value];
    }

    public function removeRole(User $user, string $role): User
    {

        if ($role === RoleEnum::MEMBER->value) {
            return $user->load('permissions', 'roles');
        }

        $user->removeRole($role);

        if (!$user->hasRole(RoleEnum::MEMBER->value)) {
            $user->assignRole(RoleEnum::MEMBER->value);
        }

        return $user->load('permissions', 'roles');
    }

    private function normalizeUsers(array|Collection $users): Collection
    {
        $users = $users instanceof Collection ? $users : collect($users);
        $ids = $users->map(fn($user) => $user instanceof User ? $user->id : $user)->toArray();
        return User::whereIn('id', $ids)->get();
    }
}
