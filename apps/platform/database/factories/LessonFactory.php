<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LessonCadence;
use App\Models\Household;
use App\Models\Lesson;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lesson> */
final class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'person_id' => Person::factory(),
            'title' => 'Mathematik',
            'weekday' => 1,
            'period_number' => null,
            'starts_at' => '07:50',
            'ends_at' => '08:40',
            'interval' => 1,
            'cadence' => LessonCadence::Calendar,
            'anchor_on' => '2026-09-07',
            'active' => true,
        ];
    }

    /** Time from the bell schedule instead of the row itself. */
    public function period(int $number): self
    {
        return $this->state([
            'period_number' => $number,
            'starts_at' => null,
            'ends_at' => null,
        ]);
    }

    /** Every other week by calendar week. */
    public function everyOtherWeek(): self
    {
        return $this->state([
            'interval' => 2,
            'cadence' => LessonCadence::Calendar,
        ]);
    }

    /** Every other school week — holidays do not count. */
    public function everyOtherSchoolWeek(): self
    {
        return $this->state([
            'interval' => 2,
            'cadence' => LessonCadence::SchoolWeeks,
        ]);
    }
}
