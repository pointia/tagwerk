<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Person;
use App\Models\PointEntry;
use App\Models\TaskCompletion;
use Carbon\CarbonImmutable;

/**
 * What can be read off points and completions: periods, streaks, badges.
 *
 * Separate from the PointLedger, which books. Here we only read and compute —
 * none of it is stored state that would have to be kept up to date.
 */
final class Gamification
{
    /**
     * Points per person in a period.
     *
     * The boundaries come in zoned and go into the query as UTC:
     * `point_entries.created_at` carries no zone, and for the household a
     * week starts at local midnight, not at midnight UTC.
     *
     * Redeeming is left out: stars are collected to be spent, and a reward
     * must not take back the week's goal. A parent's deduction counts.
     *
     * @return array<int, int>
     */
    public function pointsBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return PointEntry::query()
            ->whereBetween('created_at', [$from->utc(), $to->utc()])
            ->whereNull('reward_redemption_id')
            // A gift is not earned, neither by the one who gets it nor against the one who gives.
            ->whereNull('gift_id')
            ->selectRaw('person_id, SUM(points) AS total')
            ->groupBy('person_id')
            ->pluck('total', 'person_id')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * What someone has earned in total — without the deductions for redeeming.
     *
     * Badges should not disappear because someone treated themselves.
     *
     * @return array<int, int>
     */
    public function collected(): array
    {
        return PointEntry::query()
            ->where('points', '>', 0)
            ->whereNull('gift_id')
            ->selectRaw('person_id, SUM(points) AS total')
            ->groupBy('person_id')
            ->pluck('total', 'person_id')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * Number of consecutive days with at least one completion.
     *
     * Yesterday counts as a connection: otherwise the streak would read zero
     * every morning until the first tick of the day.
     *
     * @return array<int, int>
     */
    public function streaks(CarbonImmutable $today): array
    {
        $completions = TaskCompletion::query()
            ->whereNotNull('person_id')
            ->where('occurred_on', '>=', $today->subYear()->toDateString())
            ->get(['person_id', 'occurred_on'])
            ->groupBy('person_id');

        $streaks = [];

        foreach ($completions as $personId => $entries) {
            $days = $entries
                ->map(fn (TaskCompletion $entry): string => $entry->occurred_on->toDateString())
                ->flip();

            $day = $days->has($today->toDateString()) ? $today : $today->subDay();
            $count = 0;

            while ($days->has($day->toDateString())) {
                $count++;
                $day = $day->subDay();
            }

            $streaks[(int) $personId] = $count;
        }

        return $streaks;
    }

    /**
     * Badges are derived, not awarded.
     *
     * A table of their own would need updating on every completion and would
     * be wrong after a correction. Computed from the numbers they are always
     * right. The weekly goal is no badge: its bar on the tile already says so.
     * The icon is the badge's picture, from the same Fluent set as the tasks'.
     *
     * Each ladder shows only its highest step: “Thirty days in a row” says
     * the seven already. “Off to a start” is the step below every other.
     *
     * @return array<int, array{key: string, icon: string, name: string}>
     */
    public function badges(int $streak, int $collected): array
    {
        $badges = array_values(array_filter([
            match (true) {
                $streak >= 30 => ['key' => 'streak-30', 'icon' => '🏆', 'name' => __('Thirty days in a row')],
                $streak >= 7 => ['key' => 'streak-7', 'icon' => '🏅', 'name' => __('Seven days in a row')],
                default => null,
            },
            match (true) {
                $collected >= 500 => ['key' => 'points-500', 'icon' => '💎', 'name' => __('500 stars collected')],
                $collected >= 100 => ['key' => 'points-100', 'icon' => '💯', 'name' => __('100 stars collected')],
                default => null,
            },
        ]));

        if ($badges === [] && $collected > 0) {
            $badges[] = ['key' => 'first', 'icon' => '🌱', 'name' => __('Off to a start')];
        }

        return $badges;
    }

    /**
     * How far it still is to the reward being saved for.
     *
     * @return array{reward: string, cost: int, missing: int, share: float}|null
     */
    public function savingsGoal(Person $person, int $balance): ?array
    {
        $reward = $person->savingsReward;

        if ($reward === null || $reward->cost <= 0) {
            return null;
        }

        return [
            'reward' => (string) $reward->name,
            'cost' => (int) $reward->cost,
            'missing' => max(0, (int) $reward->cost - $balance),
            'share' => min(1.0, round(max(0, $balance) / (int) $reward->cost, 3)),
        ];
    }
}
