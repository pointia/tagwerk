<?php

declare(strict_types=1);

namespace App\Services\School;

use App\Enums\LessonChangeKind;
use Carbon\CarbonImmutable;

/**
 * A lesson on a particular day.
 *
 * Created when expanding and never stored — just like an occurrence of a
 * routine among the tasks.
 */
final readonly class LessonOccurrence
{
    public function __construct(
        public int $lessonId,
        public int $personId,
        public CarbonImmutable $date,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        /** For a replacement the new subject, otherwise the planned one. */
        public string $title,
        /** Only set for a replacement: the subject that would actually be due. */
        public ?string $originalTitle = null,
        public ?int $periodNumber = null,
        public ?LessonChangeKind $change = null,
        /** The subject's colour; null for a replacement, which is another subject. */
        public ?string $color = null,
    ) {}

    public function isCancelled(): bool
    {
        return $this->change === LessonChangeKind::Cancelled;
    }

    /** `normal`, `cancelled` or `replaced` — this is how it goes to the UI. */
    public function status(): string
    {
        return $this->change?->value ?? 'normal';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'lesson_id' => $this->lessonId,
            'person_id' => $this->personId,
            'date' => $this->date->toDateString(),
            'starts_at' => $this->startsAt->toIso8601String(),
            'ends_at' => $this->endsAt->toIso8601String(),
            'title' => $this->title,
            'original_title' => $this->originalTitle,
            'period_number' => $this->periodNumber,
            'status' => $this->status(),
            'color' => $this->color,
        ];
    }
}
