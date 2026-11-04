<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Household;
use App\Models\LessonPeriod;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LessonPeriod> */
final class LessonPeriodFactory extends Factory
{
    protected $model = LessonPeriod::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'person_id' => Person::factory(),
            'number' => 1,
            'starts_at' => '07:50',
            'ends_at' => '08:40',
        ];
    }
}
