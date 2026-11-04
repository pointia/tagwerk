<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EventOrigin;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\Household;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Event> */
final class EventFactory extends Factory
{
    protected $model = Event::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        // Relative to Carbon's now, not Faker's: tests travel in time.
        $start = fake()->dateTimeBetween(now()->subWeek(), now()->addWeeks(3));

        return [
            'household_id' => Household::factory(),
            'calendar_id' => Calendar::factory(),
            'external_id' => fake()->unique()->regexify('[a-z0-9]{26}'),
            'external_version' => '"'.fake()->regexify('[0-9]{18}').'"',
            'title' => fake()->sentence(3),
            'starts_at' => $start,
            'ends_at' => (clone $start)->modify('+1 hour'),
            'all_day' => false,
            'origin' => EventOrigin::Remote,
        ];
    }

    public function allDay(): self
    {
        return $this->state(function (array $attributes): array {
            $start = CarbonImmutable::parse($attributes['starts_at'])->startOfDay();

            return [
                'all_day' => true,
                'starts_at' => $start,
                'ends_at' => $start->addDay(),
            ];
        });
    }

    public function fromApp(string $revision = 'rev-1'): self
    {
        return $this->state(fn (): array => [
            'origin' => EventOrigin::App,
            'revision_marker' => $revision,
        ]);
    }
}
