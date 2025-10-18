<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Enums\RoleEnum;
use App\Enums\UnitEnum;
use App\Enums\PermissionEnum;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Roles
        foreach (RoleEnum::values() as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        // // 2. Create Permissions
        // foreach (UnitEnum::values() as $unit) {
        //     $unitSlug = str_replace(' ', '-', strtolower($unit));

        //     foreach (PermissionEnum::values() as $action) {
        //         $permissionName = "{$action}-{$unitSlug}";
        //         Permission::firstOrCreate(['name' => $permissionName]);
        //     }
        // }

        // // 3. Assign Permissions to Roles
        // $adminRole = Role::where('name', RoleEnum::ADMIN->value)->first();
        // $leaderRole = Role::where('name', RoleEnum::LEADER->value)->first();
        // $memberRole = Role::where('name', RoleEnum::MEMBER->value)->first();

        // // Admin gets everything
        // $adminRole->syncPermissions(Permission::all());

        // // Leader gets create, read, update (no delete)
        // $leaderPerms = Permission::query()
        //     ->where(function ($query) {
        //         $query->where('name', 'like', 'create-%')
        //             ->orWhere('name', 'like', 'read-%')
        //             ->orWhere('name', 'like', 'update-%');
        //     })
        //     ->pluck('id')
        //     ->toArray();

        // $leaderRole->syncPermissions($leaderPerms);

        // // Member gets only read
        // $memberPerms = Permission::query()
        //     ->where('name', 'like', 'read-%')
        //     ->pluck('id')
        //     ->toArray();

        // $memberRole->syncPermissions($memberPerms);
    }
}
