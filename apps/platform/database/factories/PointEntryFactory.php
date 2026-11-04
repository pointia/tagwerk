<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Household;
use App\Models\Person;
use App\Models\PointEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PointEntry> */
final class PointEntryFactory extends Factory
{
    protected $model = PointEntry::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'person_id' => Person::factory(),
            'points' => 5,
            'reason' => 'Aufgabe erledigt',
        ];
    }
}
