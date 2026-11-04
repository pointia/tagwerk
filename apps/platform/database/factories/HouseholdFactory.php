<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Household> */
final class HouseholdFactory extends Factory
{
    protected $model = Household::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Familie '.fake()->lastName(),
            'timezone' => 'Europe/Vienna',
            // The address without a location: coordinates change the theme,
            // tests that need them set them themselves.
            'street' => 'Hauptstraße 1',
            'postal_code' => '4020',
            'city' => 'Linz',
            'country_code' => 'AT',
            'holiday_region' => 'AT-4',
        ];
    }
}
