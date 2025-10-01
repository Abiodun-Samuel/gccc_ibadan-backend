<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Services\UserRolePermissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function __construct(
        private readonly UserRolePermissionService $service
    ) {
    }

    public function run(): void
    {
        $commonPassword = Hash::make('password');
        $commonPhoneNumber = '08164650987';

        // ----- Admin -----
        $admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'first_name' => 'User',
                'last_name' => 'Admin',
                'password' => $commonPassword,
                'phone_number' => $commonPhoneNumber,
            ]
        );
        $this->service->assignRoleAndSyncPermissions($admin, [
            RoleEnum::ADMIN->value,
            RoleEnum::MEMBER->value,
        ]);

        // ----- Leaders -----
        for ($i = 1; $i <= 5; $i++) {
            $leader = User::firstOrCreate(
                ['email' => "leader{$i}@gmail.com"],
                [
                    'first_name' => 'User',
                    'last_name' => "Leader {$i}",
                    'password' => $commonPassword,
                    'phone_number' => $commonPhoneNumber,
                ]
            );
            $this->service->assignRoleAndSyncPermissions($leader, [
                RoleEnum::LEADER->value,
                RoleEnum::MEMBER->value,
            ]);
        }

        // ----- Members -----
        for ($i = 1; $i <= 10; $i++) {
            $member = User::firstOrCreate(
                ['email' => "member{$i}@gmail.com"],
                [
                    'first_name' => 'User',
                    'last_name' => "Member {$i}",
                    'password' => $commonPassword,
                    'phone_number' => $commonPhoneNumber,
                ]
            );
            $this->service->assignRoleAndSyncPermissions($member, [
                RoleEnum::MEMBER->value,
            ]);
        }
    }
}
