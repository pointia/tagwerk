<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\LessonChangeKind;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\Lesson;
use App\Models\LessonChange;
use App\Models\Person;
use App\Services\School\LessonOccurrence;
use App\Services\School\LessonOccurrenceService;
use App\Support\Abilities;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The timetable on the tablet and the phone.
 *
 * Returns **occurrences**, not lessons: a rule appears once on every day it
 * applies — holidays, public holidays and cancellations are already factored
 * in.
 */
final class WallTimetableController extends Controller
{
    public function __construct(
        private readonly LessonOccurrenceService $occurrences,
        private readonly HouseholdContext $context,
        private readonly Abilities $abilities,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'person_id' => ['nullable', 'integer'],
        ]);

        $household = $this->household();
        $zone = $household->timezone ?? config('app.timezone');

        $from = isset($data['from'])
            ? CarbonImmutable::parse($data['from'], $zone)->startOfDay()
            : CarbonImmutable::now($zone)->startOfWeek();

        $to = isset($data['to'])
            ? CarbonImmutable::parse($data['to'], $zone)->startOfDay()
            : $from->addDays(6);

        $child = isset($data['person_id'])
            ? Person::query()->find($data['person_id'])
            : null;

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],

            /*
             * The children with a timetable, so the UI can draw the toggles
             * without working out itself who has one.
             */
            'people' => $this->children(),

            'periods' => $this->periods(),

            'lessons' => $this->occurrences
                ->between($household, $from, $to, $child)
                ->map(fn (LessonOccurrence $occurrence): array => [
                    ...$occurrence->toArray(),
                    'can' => ['change' => $this->abilities->mayActOn(
                        $request->user(),
                        Permission::TimetableChangeOwn,
                        [$occurrence->personId],
                    )],
                ])
                ->all(),
        ]);
    }

    /** Cancels or replaces a lesson on one day. */
    public function storeChange(Request $request, Lesson $lesson): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'kind' => ['required', 'in:cancelled,replaced'],
            'title' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
        ]);

        $this->abilities->authorizeOn($request->user(), Permission::TimetableChangeOwn, [(int) $lesson->person_id]);

        $kind = LessonChangeKind::from($data['kind']);

        $change = LessonChange::query()->updateOrCreate(
            [
                'lesson_id' => $lesson->id,
                'date' => CarbonImmutable::parse($data['date'])->toDateString(),
            ],
            [
                'household_id' => $this->context->id(),
                'kind' => $kind,
                // For a cancellation, subject and time carry no meaning.
                'title' => $kind === LessonChangeKind::Replaced ? ($data['title'] ?? null) : null,
                'starts_at' => $kind === LessonChangeKind::Replaced ? ($data['starts_at'] ?? null) : null,
                'ends_at' => $kind === LessonChangeKind::Replaced ? ($data['ends_at'] ?? null) : null,
            ],
        );

        return response()->json(['id' => $change->id], 201);
    }

    /** Takes the change back — the lesson applies as planned again. */
    public function destroyChange(Request $request, Lesson $lesson): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date']]);

        $this->abilities->authorizeOn($request->user(), Permission::TimetableChangeOwn, [(int) $lesson->person_id]);

        LessonChange::query()
            ->where('lesson_id', $lesson->id)
            ->where('date', CarbonImmutable::parse($data['date'])->toDateString())
            ->delete();

        return response()->json(status: 204);
    }

    /**
     * The children who have a timetable.
     *
     * @return array<int, array<string, mixed>>
     */
    private function children(): array
    {
        return Person::query()
            ->whereHas('lessons', fn ($query) => $query->where('active', true))
            ->ordered()
            ->get()
            ->map(fn (Person $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'color' => $person->color,
                'avatar' => $person->avatarUrl(),
            ])
            ->all();
    }

    /**
     * The bell schedule per child — the UI needs it for the row headers.
     *
     * @return array<int, array<string, mixed>>
     */
    private function periods(): array
    {
        return Person::query()
            ->with('lessonPeriods')
            ->get()
            ->flatMap(fn (Person $person): array => $person->lessonPeriods
                ->map(fn ($period): array => [
                    'person_id' => $person->id,
                    'number' => $period->number,
                    'starts_at' => substr((string) $period->starts_at, 0, 5),
                    'ends_at' => substr((string) $period->ends_at, 0, 5),
                ])
                ->all())
            ->all();
    }

    private function household(): Household
    {
        return Household::query()->findOrFail($this->context->id());
    }
}
