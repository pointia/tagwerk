<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MealKind;
use App\Models\Household;
use App\Models\MealPlanEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MealPlanEntry> */
final class MealPlanEntryFactory extends Factory
{
    protected $model = MealPlanEntry::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'planned_on' => now()->toDateString(),
            'kind' => MealKind::Dinner,
            'title' => fake()->randomElement(['Linsensuppe', 'Kaiserschmarrn', 'Gemüselasagne']),
            'position' => 0,
        ];
    }

    public function withRecipe(string $id = '42', string $provider = 'tandoor'): self
    {
        return $this->state(fn (): array => [
            'recipe_id' => $id,
            'recipe_provider' => $provider,
        ]);
    }
}
