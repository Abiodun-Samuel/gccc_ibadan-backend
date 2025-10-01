<?php

namespace Database\Seeders;

use App\Models\Attendance;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating attendance records...');

        // Use the smarter approach that avoids duplicates
        $this->createRandomAttendances(2000); // Try for more, will get max possible

        $totalRecords = Attendance::count();
        $this->command->info("Attendance seeding completed! Total records: $totalRecords");

        // Show some stats
        $this->showStats();
    }

    /**
     * Show statistics about created records
     */
    private function showStats(): void
    {
        $this->command->info("\n--- Attendance Statistics ---");

        // By service
        $byService = DB::table('attendances')
            ->select('service_id', DB::raw('count(*) as count'))
            ->groupBy('service_id')
            ->get();

        foreach ($byService as $stat) {
            $dayName = ['1' => 'Tuesday', '2' => 'Friday', '3' => 'Sunday'][$stat->service_id];
            $this->command->info("Service {$stat->service_id} ($dayName): {$stat->count} records");
        }

        // By status
        $byStatus = DB::table('attendances')
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        foreach ($byStatus as $stat) {
            $this->command->info("Status {$stat->status}: {$stat->count} records");
        }
    }

    /**
     * Create random attendance records by generating all possible combinations first
     */
    private function createRandomAttendances(int $targetCount): void
    {
        // Generate all possible unique combinations first
        $combinations = $this->generateAllPossibleCombinations();

        $this->command->info("Found " . count($combinations) . " possible unique combinations");

        if (count($combinations) == 0) {
            $this->command->error("No valid combinations found. Check your date logic.");
            return;
        }

        // Shuffle combinations to randomize
        $combinations = collect($combinations)->shuffle();

        // Take up to target count or all available combinations
        $recordsToCreate = min($targetCount, count($combinations));
        $combinations = $combinations->take($recordsToCreate);

        $this->command->info("Creating $recordsToCreate attendance records...");

        // Create records in batches
        $batchSize = 500;
        $batches = $combinations->chunk($batchSize);

        foreach ($batches as $index => $batch) {
            $records = [];

            foreach ($batch as $combo) {

                $status = fake()->randomElement(['present', 'absent']);
                $mode = $status == 'absent' ? null : fake()->randomElement(['onsite', 'online']);

                $records[] = [
                    'user_id' => $combo['user_id'],
                    'service_id' => $combo['service_id'],
                    'attendance_date' => $combo['attendance_date'],
                    'status' => $status,
                    'mode' => $mode,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert batch using DB::table for better performance
            DB::table('attendances')->insert($records);

            $this->command->info("Inserted batch " . ($index + 1) . "/" . count($batches) . " (" . count($records) . " records)");
        }
    }

    /**
     * Generate all possible unique combinations
     */
    private function generateAllPossibleCombinations(): array
    {
        $combinations = [];
        $users = [1, 2, 3, 4];
        $serviceDays = [
            1 => 'Tuesday',
            2 => 'Friday',
            3 => 'Sunday'
        ];

        // Get all valid dates for each service
        foreach ($serviceDays as $serviceId => $dayName) {
            $dates = $this->getAllDatesForDay($dayName);

            foreach ($users as $userId) {
                foreach ($dates as $date) {
                    $combinations[] = [
                        'user_id' => $userId,
                        'service_id' => $serviceId,
                        'attendance_date' => $date
                    ];
                }
            }
        }

        return $combinations;
    }

    /**
     * Get all dates for a specific day of the week within the last 3 months
     */
    private function getAllDatesForDay(string $dayName): array
    {
        $endDate = now();
        $startDate = now()->subMonths(3);

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

        return $dates;
    }

    /**
     * Create structured attendance records (alternative approach)
     * This ensures more even distribution across users and services
     */
    private function createStructuredAttendances(): void
    {
        $users = [1, 2, 3, 4];
        $services = [1, 2, 3];
        $recordsPerUserService = 30; // About 360 total base records

        foreach ($users as $userId) {
            foreach ($services as $serviceId) {
                try {
                    Attendance::factory()
                        ->count($recordsPerUserService)
                        ->forUser($userId)
                        ->forService($serviceId)
                        ->create();

                    $this->command->info("Created records for User $userId, Service $serviceId");
                } catch (\Exception $e) {
                    $this->command->warn("Some duplicates for User $userId, Service $serviceId");
                }
            }
        }

        // Add some additional random records to reach 1000+
        $additionalRecords = 700;
        $this->createRandomAttendances($additionalRecords);
    }

    /**
     * Create attendance records with specific patterns (optional method)
     */
    private function createPatternedAttendances(): void
    {
        // Create some users with high attendance
        Attendance::factory()
            ->count(50)
            ->present()
            ->forUser(1)
            ->create();

        // Create some users with mixed attendance
        Attendance::factory()
            ->count(30)
            ->absent()
            ->forUser(2)
            ->create();

        // Create records for specific services
        foreach ([1, 2, 3] as $serviceId) {
            Attendance::factory()
                ->count(100)
                ->forService($serviceId)
                ->create();
        }
    }
}
