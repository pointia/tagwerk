<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * No WithoutModelEvents: BelongsToHousehold sets household_id in the
     * creating hook. Without events the column would stay empty.
     */
    public function run(): void
    {
        $this->call([
            HouseholdSeeder::class,
            CalendarSeeder::class,
            HomeSeeder::class,
            SchoolSeeder::class,
            RewardSeeder::class,
            BusyHouseholdSeeder::class,
        ]);
    }
}
