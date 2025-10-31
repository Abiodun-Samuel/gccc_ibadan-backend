<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'name' => 'Tuesday Service',
                'description' => '',
                'day_of_week' => 'tuesday',
                'start_time' => '17:15:00',
                'is_recurring' => true,
                'service_date' => null,
            ],
            [
                'name' => 'Thursday Service',
                'description' => '',
                'day_of_week' => 'thursday',
                'start_time' => '17:15:00',
                'is_recurring' => true,
                'service_date' => null,
            ],
            [
                'name' => 'Friday Service',
                'description' => '',
                'day_of_week' => 'friday',
                'start_time' => '17:30:00',
                'is_recurring' => true,
                'service_date' => null,
            ],
            [
                'name' => 'Sunday Service',
                'description' => '',
                'day_of_week' => 'sunday',
                'start_time' => '08:00:00',
                'is_recurring' => true,
                'service_date' => null
            ],
        ];
        foreach ($services as $service) {
            Service::updateOrCreate(
                [
                    'name' => $service['name'],
                    'day_of_week' => $service['day_of_week'],
                ],
                $service
            );
        }
    }
}
