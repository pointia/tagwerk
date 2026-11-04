<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Daypart;
use App\Enums\Feature;
use App\Http\Controllers\Controller;
use App\Integrations\ProviderRegistry;
use App\Models\Household;
use App\Models\Person;
use App\Models\PointEntry;
use App\Models\Task;
use App\Services\RecurrenceRule;
use App\Services\TaskOccurrenceService;
use App\Support\Abilities;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Tasks and routines on the tablet.
 *
 * Returns **occurrences**, not tasks: a routine appears once on each of its
 * dates, and what gets ticked is always a single occurrence.
 *
 * Ticking and swapping live in their own controllers
 * (`WallTaskCompletionController`, `WallTaskAssignmentController`).
 */
final class WallTaskController extends Controller
{
    /** @var array<int, int>|null */
    private ?array $childIds = null;

    public function __construct(
        private readonly TaskOccurrenceService $occurrences,
        private readonly HouseholdContext $context,
        private readonly ProviderRegistry $registry,
        private readonly Abilities $abilities,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $household = Household::query()->findOrFail($this->context->id());
        $zone = $this->zone();
        $from = isset($data['from'])
            ? CarbonImmutable::parse($data['from'], $zone)->startOfDay()
            : CarbonImmutable::now($zone)->startOfDay();

        $to = isset($data['to'])
            ? CarbonImmutable::parse($data['to'], $zone)->startOfDay()
            : $from->addDays(6);

        $list = array_map(
            fn (array $occurrence): array => [
                'task_id' => $occurrence['task']->id,
                'title' => $occurrence['task']->title,
                'icon' => $occurrence['task']->icon,
                'notes' => $occurrence['task']->notes,
                'date' => $occurrence['date']->toDateString(),
                // Together with task and date these name the occurrence when
                // ticking; both null for an ordinary task.
                'for_person_id' => $occurrence['for']?->id,
                'daypart' => $occurrence['daypart']?->value,
                'daypart_label' => $occurrence['daypart']?->label(),
                'daypart_icon' => $occurrence['daypart']?->icon(),
                'points' => $occurrence['task']->points,
                'routine' => $occurrence['task']->isRoutine(),

                // Readable instead of RRULE, e.g. "Jeden Montag und Donnerstag"; null for one-offs.
                'recurrence' => $occurrence['task']->isRoutine()
                    ? RecurrenceRule::describeRule($occurrence['task']->rrule, $occurrence['task']->due_on)
                    : null,

                /*
                 * The occurrence's assignee, not the task's: with a rotation
                 * it changes from date to date, and the task itself then
                 * carries none at all.
                 */
                'person_id' => $occurrence['person']?->id,
                'person_name' => $occurrence['person']?->name,
                'color' => $occurrence['person']?->color,
                'avatar' => $occurrence['person']?->avatarUrl(),
                'rotating' => $occurrence['task']->rotationIds() !== [],

                // For the form when changing: whom the task stands for.
                'person_ids' => $this->personIds($occurrence['task']),
                'skip_holidays' => $occurrence['task']->skip_holidays,
                'due_on' => $occurrence['task']->due_on?->toDateString(),
                'weekdays' => $this->editableWeekdays($occurrence['task']),
                'dayparts' => array_map(fn (Daypart $part): string => $part->value, $occurrence['task']->dayparts()),
                'rotation' => $occurrence['task']->rotationIds(),
                'can' => ['complete' => $this->abilities->mayComplete(
                    $request->user(),
                    $occurrence['person'] === null ? null : (int) $occurrence['person']->id,
                )],
                'done' => $occurrence['completion'] !== null,
                'done_by' => $occurrence['completion']?->person?->name,
                'skipped' => (bool) $occurrence['completion']?->skipped,
                /*
                 * Who got the stars and how many were really booked: a task
                 * "for everyone" credits whoever did it, and a task whose
                 * stars changed later keeps what was booked back then.
                 */
                'done_by_id' => $occurrence['completion']?->person_id,
                'earned' => (int) ($occurrence['completion']?->pointEntry?->points ?? 0),

                /*
                 * Where the task comes from — null for our own. The wall
                 * should make clear that "call the dentist" comes from dad's
                 * list and was not created here.
                 */
                'origin' => $occurrence['task']->isImported()
                    ? $this->registry->forAccount($occurrence['task']->account)->label()
                    : null,
            ],
            $this->occurrences->between($from, $to),
        );

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'occurrences' => $list,
            'bonus' => $this->bonus($from, $to, $zone),

            // What a complete day is worth, so the column can show what is still to win.
            'day_bonus' => $household->has(Feature::Points) ? (int) $household->day_bonus : 0,

            /*
             * What the form on the device offers. Here and not in the
             * frontend: otherwise weekdays and dayparts would live in two
             * places and drift apart. Icons come from public/emoji/catalog.json,
             * which the admin's picker reads too.
             */
            'options' => [
                'weekdays' => RecurrenceRule::weekdays(),
                'dayparts' => array_map(fn (Daypart $part): array => [
                    'value' => $part->value,
                    'label' => ucfirst($part->label()),
                    'icon' => $part->icon(),
                ], Daypart::cases()),
            ],
        ]);
    }

    /**
     * The progress per person, without the tasks themselves.
     *
     * For the bar above the calendar. A route of its own instead of the full
     * list: the bar shows a number, and loading the whole list for it would
     * mean transferring a hundred occurrences on every page turn.
     */
    public function progress(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $zone = $this->zone();
        $from = CarbonImmutable::parse($data['from'], $zone)->startOfDay();
        $to = CarbonImmutable::parse($data['to'], $zone)->startOfDay();

        $progress = [];

        foreach ($this->occurrences->between($from, $to) as $occurrence) {
            $person = $occurrence['person'];

            // Without an assignee the occurrence counts for nobody: a bar
            // "the family is 60 percent done" says nothing about who still
            // has something to do.
            if ($person === null) {
                continue;
            }

            $progress[$person->id] ??= ['person_id' => $person->id, 'done' => 0, 'total' => 0];
            $progress[$person->id]['total']++;

            if ($occurrence['completion'] !== null) {
                $progress[$person->id]['done']++;
            }
        }

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'progress' => array_values($progress),
        ]);
    }

    /**
     * Created on the device.
     *
     * Deliberately less than the admin form: whoever stands at the tablet
     * creates in passing — what, which icon, how many points, who, on which
     * weekdays, at which time of day and whether the children take turns.
     * Monthly rules, intervals and notes stay where you sit down for them.
     */
    public function store(Request $request): JsonResponse
    {
        $task = Task::query()->create([
            ...$this->attributes($this->validated($request)),
            'active' => true,
        ]);

        return response()->json(['task_id' => $task->id], 201);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $this->ensureOwn($task);

        $data = $this->validated($request);
        $attributes = $this->attributes($data);

        // Without weekdays the form could not express the rule (monthly, every
        // other week): rule and start stay as they are.
        if (! array_key_exists('weekdays', $data)) {
            unset($attributes['rrule'], $attributes['due_on']);
        }

        /*
         * "Every child" from the admin or the starter content arrives back as
         * the list of today's children. Kept as "every child", so a child
         * added later still gets the task.
         */
        if ($task->per_person && ($task->per_person_ids ?? []) === [] && $attributes['per_person']
            && $this->sameSet($attributes['per_person_ids'], $this->childIds())) {
            $attributes['per_person_ids'] = [];
        }

        $task->update($attributes);

        return response()->json(['task_id' => $task->id]);
    }

    /**
     * Removes the task but keeps what was earned with it.
     *
     * The credit hangs off the completion, and the database would clear both
     * away with the task. A child who loses earned coins when the list is
     * tidied up would be right to complain.
     */
    public function destroy(Task $task): JsonResponse
    {
        $this->ensureOwn($task);

        $task->retire();

        return response()->json(status: 204);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],

            // 16 characters instead of 4: an emoji like 🧑‍🍳 has several parts.
            'icon' => ['nullable', 'string', 'max:16'],
            'points' => ['required', 'integer', 'min:0', 'max:999'],
            'person_id' => ['nullable', 'integer'],

            // Several people make the task one per person; wins over person_id.
            'person_ids' => ['sometimes', 'array'],
            'person_ids.*' => ['integer'],
            'skip_holidays' => ['sometimes', 'boolean'],
            'date' => ['required', 'date'],
            'weekdays' => ['sometimes', 'array'],
            'weekdays.*' => ['string', Rule::in(RecurrenceRule::DAYS)],

            // Absent keeps what is stored, so a form that does not know them
            // cannot clear them.
            'dayparts' => ['sometimes', 'array'],
            'dayparts.*' => [Rule::enum(Daypart::class)],

            // Turn order; two or more people take over from person_ids.
            'rotation' => ['sometimes', 'array'],
            'rotation.*' => ['integer'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $days = array_values(array_unique((array) ($data['weekdays'] ?? [])));
        $people = array_key_exists('person_ids', $data)
            ? array_values(array_unique(array_map(fn (mixed $id): int => (int) $this->householdPerson((int) $id), $data['person_ids'])))
            : array_filter([$this->householdPerson($data['person_id'] ?? null)]);
        $rotation = array_values(array_unique(array_map(
            fn (mixed $id): int => (int) $this->householdPerson((int) $id),
            (array) ($data['rotation'] ?? []),
        )));
        $turns = count($rotation) > 1;
        $perPerson = ! $turns && count($people) > 1;

        $attributes = [
            'title' => trim((string) $data['title']),
            'icon' => filled($data['icon'] ?? null) ? (string) $data['icon'] : null,
            'points' => (int) $data['points'],
            // A rotation names the assignee per date; the task itself has none.
            'person_id' => $turns || $perPerson ? null : ($people[0] ?? null),
            'per_person' => $perPerson,
            'per_person_ids' => $perPerson ? $people : null,
            'skip_holidays' => (bool) ($data['skip_holidays'] ?? false),
            'due_on' => CarbonImmutable::parse((string) $data['date'])->toDateString(),

            // No day tapped means one-off — on exactly this date.
            'rrule' => $days === [] ? null : (new RecurrenceRule('WEEKLY', 1, $days))->toRrule(),
        ];

        if (array_key_exists('rotation', $data)) {
            $attributes['rotation'] = $turns ? $rotation : null;
        }

        if (array_key_exists('dayparts', $data)) {
            $chosen = array_unique((array) $data['dayparts']);
            $attributes['dayparts'] = $chosen === [] ? null : array_values(array_map(
                fn (Daypart $part): string => $part->value,
                array_filter(Daypart::cases(), fn (Daypart $part): bool => in_array($part->value, $chosen, true)),
            ));
        }

        return $attributes;
    }

    /**
     * The weekdays of a rule the device form can show: weekly, every week.
     * Empty for a one-off, null for anything else — monthly rules and
     * intervals are changed in the admin.
     *
     * @return list<string>|null
     */
    private function editableWeekdays(Task $task): ?array
    {
        if (! $task->isRoutine()) {
            return [];
        }

        $rule = RecurrenceRule::parse($task->rrule);

        return $rule !== null && $rule->frequency === 'WEEKLY' && $rule->interval === 1 && $rule->weekdays !== []
            ? $rule->weekdays
            : null;
    }

    /**
     * Whom the task stands for, as the form shows it: every chosen person of
     * a task per person (all children when none were chosen), else its one
     * person.
     *
     * @return array<int, int>
     */
    private function personIds(Task $task): array
    {
        if ($task->per_person) {
            $chosen = array_map(intval(...), $task->per_person_ids ?? []);

            return $chosen !== [] ? $chosen : $this->childIds();
        }

        return $task->person_id === null ? [] : [(int) $task->person_id];
    }

    /**
     * Once per request: the list asks for every occurrence.
     *
     * @return array<int, int>
     */
    private function childIds(): array
    {
        return $this->childIds ??= Person::query()->children()->pluck('id')->map(intval(...))->all();
    }

    /**
     * @param  array<int, int>  $a
     * @param  array<int, int>  $b
     */
    private function sameSet(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }

    /**
     * As with swapping: a foreign person ID is rejected, not silently turned
     * into "everyone".
     */
    private function householdPerson(?int $id): ?int
    {
        $person = Person::idInHousehold($id);

        if ($id !== null && $person === null) {
            throw ValidationException::withMessages([
                'person_id' => __('This person does not belong to the household.'),
            ]);
        }

        return $person;
    }

    /**
     * What comes from outside belongs to the provider: the next sync would
     * pull in its state, and until then two truths would stand side by side.
     */
    private function ensureOwn(Task $task): void
    {
        if ($task->isImported()) {
            throw ValidationException::withMessages([
                'title' => __('This task comes from another service and cannot be changed here.'),
            ]);
        }
    }

    /**
     * Extra stars per person and day, so a task column adds up with the
     * stars area. Credits for tasks are in the occurrences already, and
     * spending on a reward is not something earned that day.
     *
     * The day bonus is a row of its own (`day_bonus: true`): a device that
     * just settled it replaces that row instead of adding to a sum it cannot
     * tell apart (see `keepDayBonus` in the frontend).
     *
     * @return array<int, array{person_id: int, date: string, points: int, day_bonus: bool}>
     */
    private function bonus(CarbonImmutable $from, CarbonImmutable $to, string $zone): array
    {
        $sums = [];

        PointEntry::query()
            ->whereNull('task_completion_id')
            ->whereNull('reward_redemption_id')
            ->whereNull('gift_id')
            ->where(fn ($query) => $query
                // The column has no zone and holds UTC; the household's day starts earlier there.
                ->where(fn ($booked) => $booked->whereNull('bonus_on')
                    ->where('created_at', '>=', $from->utc())
                    ->where('created_at', '<', $to->addDay()->utc()))
                // A day bonus belongs to the day it was earned for, even when ticked afterwards.
                ->orWhereBetween('bonus_on', [$from->toDateString(), $to->toDateString()]))
            ->get(['person_id', 'points', 'created_at', 'bonus_on'])
            ->each(function (PointEntry $entry) use (&$sums, $zone): void {
                $day = $entry->bonus_on?->toDateString() ?? $entry->created_at->setTimezone($zone)->toDateString();
                $key = $entry->person_id.'|'.$day.'|'.($entry->bonus_on === null ? 0 : 1);
                $sums[$key] = ($sums[$key] ?? 0) + (int) $entry->points;
            });

        $bonus = [];

        foreach ($sums as $key => $points) {
            [$personId, $date, $dayBonus] = explode('|', (string) $key);
            $bonus[] = ['person_id' => (int) $personId, 'date' => $date, 'points' => $points, 'day_bonus' => $dayBonus === '1'];
        }

        return $bonus;
    }

    private function zone(): string
    {
        return Household::query()->findOrFail($this->context->id())->timezone
            ?? (string) config('app.timezone');
    }
}
