<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PersonRole;
use App\Models\Household;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Person> */
final class PersonFactory extends Factory
{
    protected $model = Person::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => fake()->firstName(),
            'color' => fake()->hexColor(),
            'role' => PersonRole::Adult,
        ];
    }

    public function child(): self
    {
        return $this->state(fn (): array => ['role' => PersonRole::Child]);
    }

    public function guest(): self
    {
        return $this->state(fn (): array => ['role' => PersonRole::Guest]);
    }
}
