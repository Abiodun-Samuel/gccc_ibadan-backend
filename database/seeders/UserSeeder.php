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
            ['email' => 'admin@gcccibadan.org'],
            [
                'first_name' => 'Admin',
                'last_name' => 'Admin',
                'gender' => 'Male',
                'full_name' => "Admin Admin",
                'initials' => "AA",
                'password' => $commonPassword,
                'phone_number' => $commonPhoneNumber,
            ]
        );
        $this->service->assignRoleAndSyncPermissions($admin, [
            RoleEnum::ADMIN->value,
            RoleEnum::LEADER->value,
            RoleEnum::MEMBER->value,
        ]);

        // $leader = User::firstOrCreate(
        //     ['email' => "abiodunsamyemi@gmail.com"],
        //     [
        //         'first_name' => 'Samuel',
        //         'last_name' => "Abiodun",
        //         'full_name' => "Abiodun Samuel",
        //         'initials' => "AS",
        //         'gender' => 'Male',
        //         'password' => $commonPassword,
        //         'phone_number' => $commonPhoneNumber,
        //     ]
        // );
        // $this->service->assignRoleAndSyncPermissions($leader, [
        //     RoleEnum::LEADER->value,
        //     RoleEnum::MEMBER->value,
        // ]);

        // $leader1 = User::firstOrCreate(
        //     ['email' => "samyemidele@gmail.com"],
        //     [
        //         'first_name' => 'Sunkanmi',
        //         'last_name' => "Gbadegensin",
        //         'full_name' => "Sunkanmi Gbadegesin",
        //         'gender' => 'Female',
        //         'initials' => "SK",
        //         'password' => $commonPassword,
        //         'phone_number' => $commonPhoneNumber,
        //     ]
        // );
        // $this->service->assignRoleAndSyncPermissions($leader1, [
        //     RoleEnum::LEADER->value,
        //     RoleEnum::MEMBER->value,
        // ]);

        // // ----- Members -----
        // for ($i = 1; $i <= 2; $i++) {
        //     $member = User::firstOrCreate(
        //         ['email' => "member{$i}@gmail.com"],
        //         [
        //             'first_name' => "Member {$i}",
        //             'last_name' => "Member {$i}",
        //             'full_name' => "Member {$i} Member {$i}",
        //             'initials' => "MM",
        //             'password' => $commonPassword,
        //             'phone_number' => $commonPhoneNumber,
        //         ]
        //     );
        //     $this->service->assignRoleAndSyncPermissions($member, [
        //         RoleEnum::MEMBER->value,
        //     ]);
        // }
    }
}
