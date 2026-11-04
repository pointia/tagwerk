<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskCompletion;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What was completed recently — who, when, which task.
 *
 * For parents checking in the evening or on the go. Deliberately read-only
 * and without judgement: the log shows what happened and leaves the
 * conclusions to people.
 */
final class WallHistoryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'days' => ['nullable', 'integer', 'between:1,90'],
        ]);

        $days = (int) ($data['days'] ?? 14);
        $since = CarbonImmutable::now()->subDays($days - 1)->toDateString();

        $entries = TaskCompletion::query()
            ->with(['task:id,title,points', 'person:id,name,color'])
            ->where('occurred_on', '>=', $since)
            ->orderByDesc('occurred_on')
            ->orderByDesc('completed_at')
            ->limit(200)
            ->get()
            ->map(fn (TaskCompletion $completion): array => [
                'id' => $completion->id,
                'task' => $completion->task?->title,
                'points' => $completion->task?->points ?? 0,
                'person' => $completion->person?->name,
                'color' => $completion->person?->color,
                'avatar' => $completion->person?->avatarUrl(),
                'occurred_on' => $completion->occurred_on->toDateString(),
                'completed_at' => $completion->completed_at?->toIso8601String(),
            ])
            ->all();

        return response()->json(['entries' => $entries]);
    }
}
