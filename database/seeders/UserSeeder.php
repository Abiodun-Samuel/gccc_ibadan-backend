<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Services\UserRolePermissionService;
use Hash;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function __construct(
        private readonly UserRolePermissionService $service
    ) {
    }

    public function run(): void
    {
        // ----- Admin -----
        $admin1 = User::firstOrCreate(
            ['email' => 'string'],
            [
                'first_name' => 'User',
                'last_name' => 'Admin',
                'password' => Hash::make('string'),
                'phone_number' => 'string',
            ]
        );
        $this->service->assignRoleAndSyncPermissions($admin1, [RoleEnum::ADMIN->value, RoleEnum::MEMBER->value]);

        // ----- Admin -----
        $admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'first_name' => 'User',
                'last_name' => 'Admin',
                'password' => Hash::make(1503),
                'phone_number' => 1503,
            ]
        );
        $this->service->assignRoleAndSyncPermissions($admin, [RoleEnum::ADMIN->value, RoleEnum::MEMBER->value]);

        // ----- Leader -----
        $leader = User::firstOrCreate(
            ['email' => 'leader@gmail.com'],
            [
                'first_name' => 'User',
                'last_name' => 'Leader',
                'password' => Hash::make(1503),
                'phone_number' => 1503,
            ]
        );
        $this->service->assignRoleAndSyncPermissions($leader, [RoleEnum::LEADER->value, RoleEnum::MEMBER->value]);

        // ----- Member -----
        $member = User::firstOrCreate(
            ['email' => 'member@gmail.com'],
            [
                'first_name' => 'User',
                'last_name' => 'Member',
                'password' => Hash::make(1503),
                'phone_number' => 1503,
            ]
        );
        $this->service->assignRoleAndSyncPermissions($member, [RoleEnum::MEMBER->value]);
    }
}
