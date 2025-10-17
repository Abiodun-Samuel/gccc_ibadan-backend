<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            UnitSeeder::class,
            ServiceSeeder::class,
            UserSeeder::class,
            FollowUpStatusSeeder::class,
            FirstTimerSeeder::class,
                // FormSeeder::class,
            UsherAttendanceSeeder::class,
            AttendanceSeeder::class
        ]);
    }
}
