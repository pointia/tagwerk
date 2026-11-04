<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Daypart;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Jobs\PushTaskCompletionJob;
use App\Models\Person;
use App\Models\Task;
use App\Models\User;
use App\Services\DayBonus;
use App\Services\PointLedger;
use App\Services\TaskOccurrenceService;
use App\Support\Abilities;
use App\Support\HouseholdTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Ticks an occurrence and takes it back — including the credit and the report
 * to the provider the task came from.
 */
final class WallTaskCompletionController extends Controller
{
    /** How far back an account may catch up; the day view pages back as far. */
    private const int HISTORY_DAYS = 28;

    public function __construct(
        private readonly TaskOccurrenceService $occurrences,
        private readonly PointLedger $ledger,
        private readonly Abilities $abilities,
        private readonly DayBonus $dayBonus,
    ) {}

    public function store(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'person_id' => ['nullable', 'integer'],
            'skipped' => ['sometimes', 'boolean'],
            ...self::occurrenceRules(),
        ]);
        self::ensureOpen($request, $data['date']);
        [$forPersonId, $daypart] = $this->occurrence($task, $data);

        if ($data['skipped'] ?? false) {
            return $this->skip($request, $task, $data['date'], $forPersonId, $daypart);
        }

        /*
         * Without an explicit person the occurrence's assignee counts:
         * whoever taps the row "Max: take out the rubbish" means Max.
         * Otherwise his points would silently be lost — and with a rotation
         * nobody would get anything, because the task itself carries no
         * person.
         */
        $personId = $forPersonId
            // An occurrence per person is that person's, whoever taps it.
            ?? Person::idInHousehold($data['person_id'] ?? null)
            ?? $this->occurrences->assigneeFor($task, CarbonImmutable::parse($data['date']))?->id
            /*
             * Finally the fixed assignment. The derivation only applies when
             * the date really is an occurrence of the rule — when ticking a
             * moved day afterwards it is not, and without this fallback the
             * credit would silently be lost.
             */
            ?? $task->person_id;

        $actor = $request->user();

        $this->abilities->authorizeCompletion($actor, $personId === null ? null : (int) $personId);

        // A task for nobody, ticked on one's own phone, is one's own.
        $personId ??= $this->abilities->personOf($actor);

        /*
         * firstOrCreate instead of create: ticking the same occurrence twice is
         * the same fact. On the tablet the second tap happens regularly
         * because nobody waits for the feedback.
         */
        $completion = $task->completions()->firstOrCreate(
            [
                'occurred_on' => CarbonImmutable::parse($data['date'])->toDateString(),
                'for_person_id' => $forPersonId,
                'daypart' => $daypart,
            ],
            [
                'person_id' => $personId,
                'completed_at' => Carbon::now(),
            ],
        );

        /*
         * The credit is only booked on the first tick. Without this check the
         * second tap on the tablet would earn points although firstOrCreate
         * returns the same completion.
         */
        $entry = $completion->wasRecentlyCreated
            ? $this->ledger->awardForCompletion($completion)
            : null;

        if ($completion->wasRecentlyCreated && $task->isImported()) {
            PushTaskCompletionJob::dispatch((int) $task->id, true);
        }

        $owner = $this->owner($task, $forPersonId, $completion->occurred_on);
        $dayBonus = $this->dayBonus->settle($owner, $completion->occurred_on);

        return response()->json([
            'task_id' => $task->id,
            'date' => $completion->occurred_on->toDateString(),
            'done' => true,
            // The booked points, not the task's: without a person there is no
            // entry, and the wall must not announce a credit that does not
            // exist.
            'points' => $entry?->points ?? 0,
            'day_bonus' => self::dayBonus($owner, $dayBonus),
        ], 201);
    }

    /**
     * Ticked off without stars: the task could not be done that day (a
     * holiday, a sick child). Nobody did it, so nobody is credited — but the
     * day counts as finished, and the day bonus stays within reach. A parent's
     * decision: on the wall behind the PIN, otherwise a child would skip the
     * hard task and still collect the bonus.
     */
    private function skip(Request $request, Task $task, string $date, ?int $forPersonId, ?string $daypart): JsonResponse
    {
        $this->abilities->authorize($request->user(), Permission::TasksManage);

        $completion = $task->completions()->firstOrCreate(
            [
                'occurred_on' => CarbonImmutable::parse($date)->toDateString(),
                'for_person_id' => $forPersonId,
                'daypart' => $daypart,
            ],
            [
                'person_id' => null,
                'completed_at' => Carbon::now(),
                'skipped' => true,
            ],
        );

        if ($completion->wasRecentlyCreated && $task->isImported()) {
            PushTaskCompletionJob::dispatch((int) $task->id, true);
        }

        $owner = $this->owner($task, $forPersonId, $completion->occurred_on);

        return response()->json([
            'task_id' => $task->id,
            'date' => $completion->occurred_on->toDateString(),
            'done' => true,
            'points' => 0,
            'day_bonus' => self::dayBonus($owner, $this->dayBonus->settle($owner, $completion->occurred_on)),
        ], 201);
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date'], ...self::occurrenceRules()]);
        self::ensureOpen($request, $data['date']);
        [$forPersonId, $daypart] = $this->occurrence($task, $data);

        $completion = $task->completions()
            ->where('occurred_on', CarbonImmutable::parse($data['date'])->toDateString())
            ->where('for_person_id', $forPersonId)
            ->where('daypart', $daypart)
            ->first();

        $dayBonus = null;

        if ($completion !== null) {
            $this->abilities->authorizeCompletion(
                $request->user(),
                $completion->person_id === null ? null : (int) $completion->person_id,
            );

            $completion->delete();

            $owner = $this->owner($task, $forPersonId, $completion->occurred_on);
            $dayBonus = self::dayBonus($owner, $this->dayBonus->settle($owner, $completion->occurred_on));
        }

        if ($task->isImported()) {
            PushTaskCompletionJob::dispatch((int) $task->id, false);
        }

        return response()->json(['day_bonus' => $dayBonus]);
    }

    /**
     * The change of the day bonus, for the column's "+n" right away: without
     * it the stars would only appear with the next load.
     *
     * @return array{person_id: int, points: int}|null
     */
    private static function dayBonus(?int $owner, int $points): ?array
    {
        return $owner === null || $points === 0 ? null : ['person_id' => $owner, 'points' => $points];
    }

    /**
     * Whose day the occurrence belongs to — not who ticked it. The day bonus
     * rewards finishing one's own tasks; a sibling helping out does not
     * complete Max's day for Lena.
     */
    private function owner(Task $task, ?int $forPersonId, CarbonImmutable $date): ?int
    {
        $owner = $forPersonId
            ?? $this->occurrences->assigneeFor($task, $date)?->id
            ?? $task->person_id;

        return $owner === null ? null : (int) $owner;
    }

    /**
     * A past day is history on the wall: it shows what was left open and what
     * got done, and a brush in passing must not change it. An account may
     * catch up on what was forgotten — someone is signed in there, so a late
     * tick has a name behind it — but only as far back as the day view reaches.
     * Today counts in the household's zone, not the server's.
     */
    private static function ensureOpen(Request $request, string $date): void
    {
        $today = CarbonImmutable::now(HouseholdTimezone::current())->startOfDay();
        $day = CarbonImmutable::parse($date)->toDateString();

        if ($day >= $today->toDateString()) {
            return;
        }

        if (! $request->user() instanceof User) {
            throw ValidationException::withMessages(['date' => __('A past day can no longer be ticked.')]);
        }

        if ($day < $today->subDays(self::HISTORY_DAYS)->toDateString()) {
            throw ValidationException::withMessages(['date' => __('This day is too long ago to tick.')]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    private static function occurrenceRules(): array
    {
        return [
            'for_person_id' => ['nullable', 'integer'],
            'daypart' => ['nullable', Rule::enum(Daypart::class)],
        ];
    }

    /**
     * Which of the task's occurrences on that day is meant. Only what the
     * task really has counts: a stray daypart on an all-day task would
     * otherwise open a second completion for the same day.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: int|null, 1: string|null}
     */
    private function occurrence(Task $task, array $data): array
    {
        $forPersonId = null;

        if ($task->per_person) {
            $forPersonId = Person::idInHousehold(isset($data['for_person_id']) ? (int) $data['for_person_id'] : null);

            if ($forPersonId === null) {
                throw ValidationException::withMessages(['for_person_id' => __('Who is this tick for?')]);
            }
        }

        $allowed = array_map(fn (Daypart $part): string => $part->value, $task->dayparts());
        $daypart = $data['daypart'] ?? null;

        if ($allowed === []) {
            $daypart = null;
        } elseif (! in_array($daypart, $allowed, true)) {
            throw ValidationException::withMessages(['daypart' => __('The task does not have this time of day.')]);
        }

        return [$forPersonId, $daypart];
    }
}
