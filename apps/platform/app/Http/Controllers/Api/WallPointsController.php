<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\PersonRole;
use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\Person;
use App\Models\PointEntry;
use App\Models\User;
use App\Services\Gamification;
use App\Services\PointLedger;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class WallPointsController extends Controller
{
    public function __construct(
        private readonly PointLedger $ledger,
        private readonly Gamification $gamification,
        private readonly HouseholdContext $context,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /*
         * Week and month start in the household's time zone. Computed in the
         * server's zone, an entry from Monday 00:30 would still fall into the
         * previous week.
         */
        $zone = Household::query()->findOrFail($this->context->id())->timezone
            ?? (string) config('app.timezone');
        $now = CarbonImmutable::now($zone);

        $balances = $this->ledger->balances();
        $week = $this->gamification->pointsBetween($now->startOfWeek(), $now->endOfWeek());
        $month = $this->gamification->pointsBetween($now->startOfMonth(), $now->endOfMonth());
        $streaks = $this->gamification->streaks($now->startOfDay());
        $collected = $this->gamification->collected();

        $people = Person::query()
            ->where('role', PersonRole::Child->value)
            ->with('savingsReward')
            ->ordered()
            ->get()
            ->map(function (Person $person) use ($balances, $week, $month, $streaks, $collected): array {
                $balance = $balances[$person->id] ?? 0;
                $weekPoints = $week[$person->id] ?? 0;
                $streak = $streaks[$person->id] ?? 0;
                $goal = (int) $person->weekly_goal;
                $reached = $goal > 0 && $weekPoints >= $goal;

                return [
                    'id' => $person->id,
                    'name' => $person->name,
                    'color' => $person->color,
                    'avatar' => $person->avatarUrl(),
                    'balance' => $balance,
                    'week_points' => $weekPoints,
                    'month_points' => $month[$person->id] ?? 0,
                    'streak' => $streak,
                    'weekly_goal' => $goal,
                    'goal_reached' => $reached,
                    'badges' => $this->gamification->badges($streak, $collected[$person->id] ?? 0),
                    'saving' => $this->gamification->savingsGoal($person, $balance),
                ];
            })
            ->all();

        $filter = $request->validate([
            'person' => ['nullable', 'integer'],
            'sign' => ['nullable', 'in:plus,minus'],
        ]);

        [$entries, $hasMore] = $this->entries(
            max(1, $request->integer('page', 1)),
            isset($filter['person']) ? (int) $filter['person'] : null,
            $filter['sign'] ?? null,
        );

        return response()->json([
            'people' => $people,
            'entries' => $entries,
            'has_more' => $hasMore,
        ]);
    }

    /**
     * A correction by a parent — behind the PIN.
     *
     * Always an additional entry, never a change to an existing one. A
     * silently changed balance would be incomprehensible to a child, and
     * traceability is exactly what the ledger is for.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'person_id' => ['required', 'integer'],
            // Taking away is one star at a time; larger corrections belong in the admin's history.
            'points' => ['required', 'integer', 'not_in:0', 'between:-1,1000'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $person = Person::query()->find($data['person_id']);

        if ($person === null) {
            throw ValidationException::withMessages(['person_id' => __('Unknown person.')]);
        }

        // No debts: below zero no reward is in reach, and then nothing pays off any more.
        if ($data['points'] < 0 && PointEntry::balanceFor((int) $person->id) + $data['points'] < 0) {
            throw ValidationException::withMessages(['points' => __('The balance cannot go below zero.')]);
        }

        $entry = $this->ledger->book(
            (int) $person->id,
            (int) $data['points'],
            $data['reason'],
            $request->user() instanceof User ? (int) $request->user()->getKey() : null,
        );

        return response()->json([
            'id' => $entry->id,
            'person_id' => $entry->person_id,
            'points' => $entry->points,
            'reason' => $entry->reason,
        ], 201);
    }

    /**
     * Stars one child gives another — booked by a parent, like a correction.
     *
     * At most what the giver has: a gift is not a loan.
     */
    public function gift(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_person_id' => ['required', 'integer'],
            'to_person_id' => ['required', 'integer', 'different:from_person_id'],
            'points' => ['required', 'integer', 'between:1,1000'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $children = Person::query()
            ->where('role', PersonRole::Child->value)
            ->whereKey([$data['from_person_id'], $data['to_person_id']])
            ->get()
            ->keyBy('id');

        $from = $children->get($data['from_person_id']);
        $to = $children->get($data['to_person_id']);

        if ($from === null || $to === null) {
            throw ValidationException::withMessages(['from_person_id' => __('Stars go from one child to another.')]);
        }

        $result = $this->ledger->gift(
            $from,
            $to,
            (int) $data['points'],
            isset($data['note']) ? trim((string) $data['note']) : null,
            $request->user() instanceof User ? (int) $request->user()->getKey() : null,
        );

        if (is_int($result)) {
            throw ValidationException::withMessages([
                'points' => trans_choice(':name has only :count star to give.|:name has only :count stars to give.', $result, [
                    'name' => $from->name,
                ]),
            ]);
        }

        [$taken, $credited] = $result;

        return response()->json([
            'gift_id' => $taken->gift_id,
            'from' => ['person_id' => $taken->person_id, 'points' => $taken->points, 'reason' => $taken->reason],
            'to' => ['person_id' => $credited->person_id, 'points' => $credited->points, 'reason' => $credited->reason],
        ], 201);
    }

    /**
     * One page of the history, plus whether there is more.
     *
     * One entry more is fetched than delivered: that answers the question of
     * further pages without a second count query. Filtered here, not on the
     * wall: paging on through "older bookings" has to stay filtered.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: bool}
     */
    private function entries(int $page, ?int $personId = null, ?string $sign = null): array
    {
        $perPage = 20;

        $entries = PointEntry::query()
            ->with('person')
            ->when($personId !== null, fn ($query) => $query->where('person_id', $personId))
            ->when($sign === 'plus', fn ($query) => $query->where('points', '>', 0))
            ->when($sign === 'minus', fn ($query) => $query->where('points', '<', 0))
            ->latest('id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage + 1)
            ->get();

        return [
            $entries
                ->take($perPage)
                ->map(fn (PointEntry $entry): array => [
                    'id' => $entry->id,
                    'person_id' => $entry->person_id,
                    'person' => $entry->person?->name,
                    'avatar' => $entry->person?->avatarUrl(),
                    'points' => $entry->points,
                    'reason' => $entry->reason,
                    'created_at' => $entry->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            $entries->count() > $perPage,
        ];
    }
}
