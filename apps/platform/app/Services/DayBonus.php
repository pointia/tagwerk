<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Feature;
use App\Models\Household;
use App\Models\PointEntry;
use App\Support\HouseholdContext;
use App\Support\Locale;
use Carbon\CarbonImmutable;

/**
 * Extra stars for a day on which a person has done all of their own tasks.
 *
 * Settled after every tick and every undo: the bonus exists exactly while the
 * day is complete. A day without tasks earns nothing — there was nothing to
 * finish — and an occurrence for nobody ("Für alle") holds nobody back.
 *
 * `settle()` returns what changed — the stars booked (positive), taken back
 * (negative) or 0 — so the wall can show and celebrate it with the tick
 * instead of after the next load.
 */
final class DayBonus
{
    public function __construct(
        private readonly TaskOccurrenceService $occurrences,
        private readonly HouseholdContext $context,
    ) {}

    public function settle(?int $personId, CarbonImmutable $date): int
    {
        if ($personId === null) {
            return 0;
        }

        $day = CarbonImmutable::parse($date->toDateString());
        $existing = PointEntry::query()
            ->where('person_id', $personId)
            ->whereDate('bonus_on', $day->toDateString())
            ->first();

        $household = Household::query()->find($this->context->id());
        $points = $household !== null && $household->has(Feature::Points) ? (int) $household->day_bonus : 0;

        if ($points <= 0 || ! $this->complete($personId, $day)) {
            $existing?->delete();

            return $existing === null ? 0 : -(int) $existing->points;
        }

        if ($existing !== null) {
            return 0;
        }

        // createOrFirst: two taps at once meet at the unique index, not in a second booking.
        $entry = PointEntry::query()->createOrFirst(
            ['person_id' => $personId, 'bonus_on' => $day->toDateString()],
            [
                'points' => $points,
                // Stored in the household's language, like every other stored text.
                'reason' => Locale::using($household->locale, fn (): string => __('All tasks of the day done')),
            ],
        );

        return $entry->wasRecentlyCreated ? $points : 0;
    }

    private function complete(int $personId, CarbonImmutable $day): bool
    {
        $own = array_filter(
            $this->occurrences->between($day, $day),
            fn (array $occurrence): bool => $occurrence['person']?->id === $personId,
        );

        foreach ($own as $occurrence) {
            if ($occurrence['completion'] === null) {
                return false;
            }
        }

        return $own !== [];
    }
}
