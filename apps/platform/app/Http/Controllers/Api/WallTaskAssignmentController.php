<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\Task;
use App\Services\TaskOccurrenceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Who takes over a single occurrence, deviating from the rule.
 */
final class WallTaskAssignmentController extends Controller
{
    public function __construct(
        private readonly TaskOccurrenceService $occurrences,
    ) {}

    /**
     * Assigns a single week differently.
     *
     * Leaves the rule alone: a shifted timetable usually affects one date,
     * and the rotation should carry on unchanged afterwards instead of
     * shifting by one place.
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'person_id' => ['required', 'integer'],
        ]);

        $person = Person::idInHousehold($data['person_id']);

        if ($person === null) {
            throw ValidationException::withMessages([
                'person_id' => __('This person does not belong to the household.'),
            ]);
        }

        $date = CarbonImmutable::parse($data['date'])->toDateString();

        $assignment = $task->assignments()->updateOrCreate(
            ['occurred_on' => $date],
            ['person_id' => $person],
        );

        return response()->json([
            'task_id' => $task->id,
            'date' => $date,
            'person_id' => $assignment->person_id,
            'person_name' => $assignment->person?->name,
            'color' => $assignment->person?->color,
            'avatar' => $assignment->person?->avatarUrl(),
        ]);
    }

    /**
     * Back to the rule — the deviation disappears instead of being frozen.
     *
     * Responds with the assignee that applies again instead of 204: only the
     * server derives it, and without it the wall would keep showing the
     * swapped name until the next poll.
     */
    public function destroy(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date']]);

        $date = CarbonImmutable::parse($data['date']);

        $task->assignments()
            ->where('occurred_on', $date->toDateString())
            ->delete();

        $person = $this->occurrences->assigneeFor($task->refresh(), $date);

        return response()->json([
            'task_id' => $task->id,
            'date' => $date->toDateString(),
            'person_id' => $person?->id,
            'person_name' => $person?->name,
            'color' => $person?->color,
            'avatar' => $person?->avatarUrl(),
        ]);
    }
}
