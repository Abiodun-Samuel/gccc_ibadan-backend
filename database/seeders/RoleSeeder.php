<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::create(['name' => UserRole::SUPER_ADMIN->value, 'guard_name' => 'api']);
        Role::create(['name' => UserRole::ADMIN->value, 'guard_name' => 'api']);
        Role::create(['name' => UserRole::LEADER->value, 'guard_name' => 'api']);
        Role::create(['name' => UserRole::MEMBER->value, 'guard_name' => 'api']);
        Role::create(['name' => UserRole::FIRST_TIMER->value, 'guard_name' => 'api']);
    }
}
