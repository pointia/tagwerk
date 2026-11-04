<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Daypart;
use App\Enums\PersonRole;
use App\Models\Person;
use App\Models\SchoolClosure;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskCompletion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RRule\RRule;

/**
 * Expands tasks into occurrences.
 *
 * A one-off task has exactly one occurrence on its due date, a routine one per
 * date of its rule. Occurrences are **not** stored: a table of precomputed
 * dates would need updating on every rule change and would grow unwieldy
 * after a year of "every second Thursday".
 *
 * For the same reason the assignee is derived instead of stored — see
 * assignee().
 */
final class TaskOccurrenceService
{
    /**
     * One task and date can yield several occurrences: one per person for
     * tasks per person, and one per time of day. `for` is the person an
     * occurrence per person belongs to, and then also its `person`.
     *
     * @return array<int, array{task: Task, date: CarbonImmutable, completion: TaskCompletion|null, person: Person|null, for: Person|null, daypart: Daypart|null}>
     */
    public function between(CarbonImmutable $from, CarbonImmutable $to): array
    {
        /*
         * Cut down to plain calendar dates. Tasks have no time of day: "done
         * on Monday" applies all day. If the boundary came in zoned, the 16th
         * at midnight Vienna time would really be the 15th at 22:00 UTC — and
         * the edge day would drop out although it was requested.
         */
        $from = CarbonImmutable::parse($from->toDateString());
        $to = CarbonImmutable::parse($to->toDateString());

        $occurrences = [];
        $people = $this->people();
        $closures = $this->closures($from, $to);

        $tasks = Task::query()
            ->where('active', true)
            ->with(['person', 'completions.person', 'completions.pointEntry', 'assignments', 'account'])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        foreach ($tasks as $task) {
            $owners = $task->per_person ? $this->owners($task, $people) : [null];
            $dayparts = $task->dayparts() ?: [null];

            foreach ($this->dates($task, $from, $to) as [$date, $number]) {
                $assignee = $task->per_person ? null : $this->assignee($task, $date, $number, $people);

                foreach ($dayparts as $daypart) {
                    foreach ($owners as $owner) {
                        if ($task->skip_holidays && self::closed($closures, $date, $owner ?? $assignee)) {
                            continue;
                        }

                        $occurrences[] = [
                            'task' => $task,
                            'date' => $date,
                            'completion' => $task->completions->first(
                                fn (TaskCompletion $completion): bool => $completion->occurred_on->isSameDay($date)
                                    && $completion->daypart === $daypart?->value
                                    && $completion->for_person_id === $owner?->id,
                            ),
                            'person' => $owner ?? $assignee,
                            'for' => $owner,
                            'daypart' => $daypart,
                        ];
                    }
                }
            }
        }

        // Within a day by time of day first: the evening round comes last.
        usort($occurrences, fn (array $a, array $b): int => [$a['date']->timestamp, self::dayOrder($a['daypart']), $a['task']->position, $a['task']->id]
            <=> [$b['date']->timestamp, self::dayOrder($b['daypart']), $b['task']->position, $b['task']->id]);

        return $occurrences;
    }

    /**
     * Who is responsible on a single day.
     *
     * A route of its own instead of between(): when ticking, the controller
     * needs exactly one task, and between() would load all of them for it.
     */
    public function assigneeFor(Task $task, CarbonImmutable $date): ?Person
    {
        $date = CarbonImmutable::parse($date->toDateString());

        foreach ($this->dates($task, $date, $date) as [$occurrenceDate, $number]) {
            return $this->assignee($task, $occurrenceDate, $number, $this->people());
        }

        return null;
    }

    /**
     * The order is the occurrence's running number since the rule began,
     * modulo the length of the list.
     *
     * Not "whoever did it last passes it on": then who is next would only be
     * known after ticking, and the wall could show nothing in advance. A
     * skipped date also shifts nobody this way — whoever misses their week
     * is still done.
     *
     * @param  Collection<int, Person>  $people
     */
    private function assignee(
        Task $task,
        CarbonImmutable $date,
        int $number,
        Collection $people,
    ): ?Person {
        $override = $task->assignments
            ->first(fn (TaskAssignment $assignment): bool => $assignment->occurred_on->isSameDay($date));

        if ($override !== null) {
            return $people->get($override->person_id);
        }

        $rotation = $task->rotationIds();

        if ($rotation === []) {
            return $task->person;
        }

        return $people->get($rotation[$number % count($rotation)]);
    }

    /**
     * Whom a task per person stands for: the chosen people, or without a
     * choice every child — so a child added later has it right away.
     *
     * @param  Collection<int, Person>  $people
     * @return array<int, Person>
     */
    private function owners(Task $task, Collection $people): array
    {
        $chosen = array_map(intval(...), $task->per_person_ids ?? []);

        return $people
            ->filter(fn (Person $person): bool => $chosen === []
                ? $person->role === PersonRole::Child
                : in_array((int) $person->id, $chosen, true))
            ->sortBy('name')
            ->values()
            ->all();
    }

    /** @return Collection<int, SchoolClosure> */
    private function closures(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return SchoolClosure::query()
            ->where('starts_on', '<=', $to->toDateString())
            ->where('ends_on', '>=', $from->toDateString())
            ->get();
    }

    /**
     * Whether a day is off school for that person. A closure without a
     * person holds for every child; one with a person only for that child —
     * siblings at different schools have different holidays, and only the
     * one on holiday pauses.
     *
     * @param  Collection<int, SchoolClosure>  $closures
     */
    private static function closed(Collection $closures, CarbonImmutable $date, ?Person $person): bool
    {
        return $closures->contains(fn (SchoolClosure $closure): bool => $date->betweenIncluded($closure->starts_on, $closure->ends_on)
            && ($closure->person_id === null || $closure->person_id === $person?->id));
    }

    /** "Any time" sorts before the times of day. */
    private static function dayOrder(?Daypart $daypart): int
    {
        return $daypart === null ? -1 : (int) array_search($daypart, Daypart::cases(), true);
    }

    /** @return Collection<int, Person> */
    private function people(): Collection
    {
        return Person::query()->get()->keyBy('id');
    }

    /**
     * Dates together with their running number since the rule began.
     *
     * Counting starts at `due_on`, not at the requested period: otherwise the
     * rotation would restart on every page turn, and whoever flips a week
     * ahead on the tablet would see a different person than yesterday.
     *
     * @return array<int, array{0: CarbonImmutable, 1: int}>
     */
    private function dates(Task $task, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! $task->isRoutine()) {
            $due = $task->due_on;

            return $due !== null && $due->betweenIncluded($from, $to) ? [[$due, 0]] : [];
        }

        /*
         * Without a due date the rule starts at the beginning of the period.
         * For "every Monday" that is equivalent; for "every second Monday" the
         * reference point to count from would otherwise be missing.
         */
        $start = $task->due_on ?? $from;

        $rule = new RRule(
            'DTSTART:'.$start->format('Ymd').PHP_EOL.'RRULE:'.$task->rrule,
        );

        /*
         * From the start instead of `from`, because only that yields the
         * running number. That is more computation than needed when a daily
         * routine has run for years — but these are date objects in memory,
         * not queries, and the alternative would be a stored number that
         * turns wrong on every rule change.
         */
        $all = $rule->getOccurrencesBetween($start->toDateTime(), $to->toDateTime());
        $hits = [];

        foreach ($all as $number => $occurrence) {
            $date = CarbonImmutable::parse($occurrence->format('Y-m-d'));

            if ($date->betweenIncluded($from, $to)) {
                $hits[] = [$date, $number];
            }
        }

        return $hits;
    }
}
