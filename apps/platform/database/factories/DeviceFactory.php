<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeviceKind;
use App\Models\Device;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Device> */
final class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => 'Wandtablet '.fake()->word(),
            'kind' => DeviceKind::Wall,
        ];
    }
}
