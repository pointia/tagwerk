<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DashboardWidget;
use App\Models\Device;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DashboardWidget> */
final class DashboardWidgetFactory extends Factory
{
    protected $model = DashboardWidget::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            // A tile always belongs to one dashboard; tests pass the device they pair.
            'device_id' => Device::factory(),
            'type' => 'clock',
            'x' => 0,
            'y' => 0,
            'w' => 3,
            'h' => 2,
            'settings' => null,
            'position' => 0,
        ];
    }
}
