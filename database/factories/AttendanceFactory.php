<?php

namespace Database\Factories;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Define service to day mapping
        $serviceDays = [
            1 => 'Tuesday',   // Service ID 1 for Tuesday
            2 => 'Friday',    // Service ID 2 for Friday
            3 => 'Sunday',    // Service ID 3 for Sunday
        ];

        // Pick a random service
        $serviceId = $this->faker->randomElement([1, 2, 3]);
        $dayName = $serviceDays[$serviceId];

        // Generate attendance date for the corresponding day within last 3 months
        $attendanceDate = $this->getRandomDateForDay($dayName);

        return [
            'user_id' => $this->faker->numberBetween(1, 4),
            'service_id' => $serviceId,
            'attendance_date' => $attendanceDate,
            'status' => $this->faker->randomElement(['present', 'absent']),
            'mode' => $this->faker->randomElement(['onsite', 'online', null]),
        ];
    }

    /**
     * Get a random date for a specific day of the week within the last 3 months
     */
    private function getRandomDateForDay(string $dayName): string
    {
        $endDate = now();
        $startDate = now()->subMonths(3);

        // Get all dates for the specific day within the range
        $dates = [];
        $current = $startDate->copy();

        // Move to the first occurrence of the desired day
        while ($current->format('l') !== $dayName) {
            $current->addDay();
        }

        // Collect all dates for this day within the range
        while ($current->lte($endDate)) {
            $dates[] = $current->format('Y-m-d');
            $current->addWeek(); // Move to next week's same day
        }

        // Return a random date from the collected dates
        return $this->faker->randomElement($dates);
    }

    /**
     * State for present attendance
     */
    public function present(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'present',
            'mode' => $this->faker->randomElement(['onsite', 'online']),
        ]);
    }

    /**
     * State for absent attendance
     */
    public function absent(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'absent',
            'mode' => null,
        ]);
    }

    /**
     * State for specific service
     */
    public function forService(int $serviceId): static
    {
        $serviceDays = [
            1 => 'Tuesday',
            2 => 'Friday',
            3 => 'Sunday',
        ];

        $dayName = $serviceDays[$serviceId] ?? 'Sunday';

        return $this->state(fn(array $attributes) => [
            'service_id' => $serviceId,
            'attendance_date' => $this->getRandomDateForDay($dayName),
        ]);
    }

    /**
     * State for specific user
     */
    public function forUser(int $userId): static
    {
        return $this->state(fn(array $attributes) => [
            'user_id' => $userId,
        ]);
    }
}
