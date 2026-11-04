<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Household;
use App\Models\Person;
use App\Models\Reward;
use App\Models\RewardRedemption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RewardRedemption> */
final class RewardRedemptionFactory extends Factory
{
    protected $model = RewardRedemption::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'reward_id' => Reward::factory(),
            'person_id' => Person::factory(),
            'cost' => 20,
        ];
    }
}
