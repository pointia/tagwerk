<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Household;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Recipe> */
final class RecipeFactory extends Factory
{
    protected $model = Recipe::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'title' => $this->faker->words(2, true),
            'steps' => null,
            'servings' => 4,
            'ingredients' => [
                ['amount' => 500, 'amount_max' => null, 'unit' => 'g', 'name' => 'Mehl', 'note' => null, 'group' => null],
                ['amount' => 4, 'amount_max' => null, 'unit' => null, 'name' => 'Eier', 'note' => null, 'group' => null],
            ],
        ];
    }
}
