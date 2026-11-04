<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LessonChangeKind;
use App\Models\Household;
use App\Models\Lesson;
use App\Models\LessonChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LessonChange> */
final class LessonChangeFactory extends Factory
{
    protected $model = LessonChange::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'lesson_id' => Lesson::factory(),
            'date' => '2026-09-07',
            'kind' => LessonChangeKind::Cancelled,
            'title' => null,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function replacedBy(string $subject, ?string $from = null, ?string $to = null): self
    {
        return $this->state([
            'kind' => LessonChangeKind::Replaced,
            'title' => $subject,
            'starts_at' => $from,
            'ends_at' => $to,
        ]);
    }
}
