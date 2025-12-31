<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Enums\RoleEnum;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RoleEnum::values() as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }
        // php artisan db:seed --class=RolesAndPermissionsSeeder
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $permissions = [
            'view-prayer',
            'view-question',
            'view-attendance-records',
            'edit-attendance-records',
            'delete-attendance-records',
        ];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }
}
