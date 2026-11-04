<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Checklist;
use App\Models\Household;
use App\Models\ListItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ListItem> */
final class ListItemFactory extends Factory
{
    protected $model = ListItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'list_id' => Checklist::factory(),
            'title' => fake()->randomElement(['Milch', 'Brot', 'Äpfel', 'Zahnpasta', 'Kaffee']),
            'position' => 0,
        ];
    }

    public function done(): self
    {
        return $this->state(fn (): array => ['done_at' => now()]);
    }
}
