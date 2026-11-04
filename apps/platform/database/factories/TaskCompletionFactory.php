<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Household;
use App\Models\Task;
use App\Models\TaskCompletion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaskCompletion> */
final class TaskCompletionFactory extends Factory
{
    protected $model = TaskCompletion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'task_id' => Task::factory(),
            'occurred_on' => now()->toDateString(),
            'completed_at' => now(),
        ];
    }
}
