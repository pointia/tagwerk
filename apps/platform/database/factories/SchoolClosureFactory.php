<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Household;
use App\Models\SchoolClosure;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SchoolClosure> */
final class SchoolClosureFactory extends Factory
{
    protected $model = SchoolClosure::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'person_id' => null,
            'title' => 'Herbstferien',
            'starts_on' => '2026-10-27',
            'ends_on' => '2026-10-31',
        ];
    }
}
