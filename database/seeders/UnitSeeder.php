<?php

namespace Database\Seeders;

use App\Enums\UnitEnum;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (UnitEnum::values() as $unitName) {
            Unit::firstOrCreate(['name' => $unitName]);
        }
    }
}
