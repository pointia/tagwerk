<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Household;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Task> */
final class TaskFactory extends Factory
{
    protected $model = Task::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'title' => fake()->randomElement(['Müll rausbringen', 'Tisch decken', 'Zimmer aufräumen']),
            'due_on' => now()->toDateString(),
            'points' => 0,
            'active' => true,
            'position' => 0,
        ];
    }

    /** A routine is a task with a rule — not a kind of its own. */
    public function routine(string $rrule = 'FREQ=WEEKLY;BYDAY=MO,WE,FR'): self
    {
        return $this->state(fn (): array => ['rrule' => $rrule]);
    }
}
