<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * The summary of a day: what was done, by whom, and what stayed open.
 *
 * Counts occurrences, not tasks: a daily routine is due once every day, and
 * that is exactly what matters in the evening.
 */
final readonly class DailySummary
{
    public function __construct(private TaskOccurrenceService $occurrences) {}

    /**
     * @return array{done: int, open: int, total: int, by_person: array<string, int>}
     */
    public function for(CarbonImmutable $day): array
    {
        $occurrences = $this->occurrences->between($day->startOfDay(), $day->startOfDay());

        $byPerson = [];
        $done = 0;

        foreach ($occurrences as $occurrence) {
            if ($occurrence['completion'] === null) {
                continue;
            }

            $done++;
            $name = $occurrence['completion']->person?->name ?? __('without a name');
            $byPerson[$name] = ($byPerson[$name] ?? 0) + 1;
        }

        arsort($byPerson);

        return [
            'done' => $done,
            'open' => count($occurrences) - $done,
            'total' => count($occurrences),
            'by_person' => $byPerson,
        ];
    }

    /**
     * A sentence for the notification, or null when there is nothing to report.
     *
     * No tasks, no message: a "0 of 0 done" notification would only be
     * evening noise.
     */
    public function text(CarbonImmutable $day): ?string
    {
        $summary = $this->for($day);

        if ($summary['total'] === 0) {
            return null;
        }

        $sentence = __(':done of :total done', ['done' => $summary['done'], 'total' => $summary['total']]);

        if ($summary['by_person'] !== []) {
            $who = [];

            foreach ($summary['by_person'] as $name => $count) {
                $who[] = "{$name} {$count}";
            }

            $sentence .= ' — '.implode(', ', $who);
        }

        return $sentence;
    }
}
