<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Services\UserRoleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UserSeeder extends Seeder
{

    public function __construct(
        private readonly UserRoleService $service
    ) {}

    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::table('users')->truncate();

        $users = [
            [
                'id' => '1',
                'first_name' => 'Admin',
                'last_name' => 'Admin',
                'email' => 'admin@gcccibadan.org',
                'phone_number' => '08164650987',
                'gender' => 'Male',
                'address' => null,
                'community' => null,
                'status' => null,
                'email_verified_at' => null,
                'password' => '',
                'remember_token' => null,
                'date_of_birth' => null,
                'date_of_visit' => null,
                'created_at' => '31-08-25 1:02',
                'updated_at' => '31-08-25 1:02',
            ],
            [
                'id' => '2',
                'first_name' => 'Sam 2',
                'last_name' => 'Sam 2',
                'email' => 'abiodunsamyemi@gmail.com',
                'phone_number' => '08164650981',
                'gender' => 'Male',
                'address' => null,
                'community' => null,
                'status' => null,
                'email_verified_at' => null,
                'password' => '',
                'remember_token' => null,
                'date_of_birth' => null,
                'date_of_visit' => null,
                'created_at' => '31-08-25 1:02',
                'updated_at' => '31-08-25 1:02',
            ],
            [
                'id' => '3',
                'first_name' => 'Sam 3',
                'last_name' => 'Sam 3',
                'email' => 'samyemidele@gmail.com',
                'phone_number' => '08164650983',
                'gender' => 'Male',
                'address' => null,
                'community' => null,
                'status' => null,
                'email_verified_at' => null,
                'password' => '',
                'remember_token' => null,
                'date_of_birth' => null,
                'date_of_visit' => null,
                'created_at' => '31-08-25 1:02',
                'updated_at' => '31-08-25 1:02',
            ],
            [
                'id' => '4',
                'first_name' => 'Sam 4',
                'last_name' => 'Sam 4',
                'email' => 'abiodundigitalhub@gmail.com',
                'phone_number' => '08164650985',
                'gender' => 'Male',
                'address' => null,
                'community' => null,
                'status' => null,
                'email_verified_at' => null,
                'password' => '',
                'remember_token' => null,
                'date_of_birth' => null,
                'date_of_visit' => null,
                'created_at' => '31-08-25 1:02',
                'updated_at' => '31-08-25 1:02',
            ],
            [
                'id' => '5',
                'first_name' => 'Sam 5',
                'last_name' => 'Sam 5',
                'email' => 'contact@superoagrobase.com',
                'phone_number' => '08164650983',
                'gender' => 'Male',
                'address' => null,
                'community' => null,
                'status' => null,
                'email_verified_at' => null,
                'password' => '',
                'remember_token' => null,
                'date_of_birth' => null,
                'date_of_visit' => null,
                'created_at' => '31-08-25 1:02',
                'updated_at' => '31-08-25 1:02',
            ]
        ];

        foreach ($users as $userData) {
            $userData['password'] = Hash::make($userData['phone_number']);
            $userData['created_at'] = now();
            $userData['updated_at'] = now();
            $userData['status'] = 'active';
            $userData['date_of_visit'] = null;
            $userData['date_of_birth'] = null;

            $user = User::create($userData);
            if ($userData['email'] == 'admin@gcccibadan.org'  || $userData['email'] == 'Opeyemiadebowale1759@gmail.com' || $userData['email'] == 'abiodunsamyemi@gmail.com') {
                $this->service->assignUserRoles($user, [
                    RoleEnum::ADMIN->value,
                    RoleEnum::LEADER->value,
                    RoleEnum::MEMBER->value,
                ]);
            } else {
                $this->service->assignUserRoles($user, [
                    RoleEnum::MEMBER->value,
                ]);
            }
        }
        //  'email' => "ajikanletajworth@gmail.com",
        // 'phone_number' => "07068727719",
        // $user = User::create([
        //     'first_name' => 'Admin',
        //     'last_name' => 'admin',
        //     'email' => 'admin@gcccibadan.org',
        //     'phone_number' => '08164650987',
        //     'gender' => 'Male',
        //     'password' => Hash::make('password')
        // ]);
        // $user2 = User::create([
        //     'id' => 18,
        //     'first_name' => 'Admin 2',
        //     'last_name' => 'admin 2',
        //     "email" => "user@example.com",
        //     'phone_number' => '08164650987',
        //     'gender' => 'Male',
        //     'password' => Hash::make('string')
        // ]);

        // $this->service->assignUserRoles($user, [
        //     RoleEnum::ADMIN->value,
        //     RoleEnum::LEADER->value,
        //     RoleEnum::MEMBER->value,
        // ]);
        // $this->service->assignUserRoles($user2, [
        //     RoleEnum::ADMIN->value,
        //     RoleEnum::LEADER->value,
        //     RoleEnum::MEMBER->value,
        // ]);
        Schema::enableForeignKeyConstraints();
    }
}
