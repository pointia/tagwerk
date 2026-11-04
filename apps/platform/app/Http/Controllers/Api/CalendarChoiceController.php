<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Which calendars an account shows on the phone.
 *
 * Only among those the household shows at all (`is_active`): what the admin
 * switched off is not on offer. Stored as the hidden ones, so a calendar the
 * admin switches on later shows up by itself.
 */
final class CalendarChoiceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($this->user($request)));
    }

    public function update(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $data = $request->validate([
            'hidden' => ['present', 'array'],
            // The household scope keeps foreign calendars out of the check.
            'hidden.*' => ['integer', Rule::in(Calendar::query()->where('is_active', true)->pluck('id')->all())],
        ]);

        $user->update(['hidden_calendar_ids' => array_values(array_unique(array_map('intval', $data['hidden'])))]);

        return response()->json($this->payload($user));
    }

    /** @return array<string, mixed> */
    private function payload(User $user): array
    {
        $hidden = array_map('intval', $user->hidden_calendar_ids ?? []);

        return [
            'calendars' => Calendar::query()
                ->where('is_active', true)
                ->orderBy('summary')
                ->get()
                ->map(fn (Calendar $calendar): array => [
                    'id' => $calendar->id,
                    'summary' => $calendar->summary,
                    'color' => $calendar->color,
                    'shown' => ! in_array($calendar->id, $hidden, true),
                ])
                ->all(),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
