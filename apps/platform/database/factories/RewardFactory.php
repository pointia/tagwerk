<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Household;
use App\Models\Reward;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Reward> */
final class RewardFactory extends Factory
{
    protected $model = Reward::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => fake()->randomElement(['Kinoabend', 'Eis', 'Eine Stunde länger aufbleiben']),
            'cost' => 20,
            'active' => true,
            'position' => 0,
        ];
    }
}
