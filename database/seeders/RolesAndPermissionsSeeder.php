<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Enums\RoleEnum;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Roles
        foreach (RoleEnum::values() as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }
    }
}
