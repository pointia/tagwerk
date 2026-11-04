<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Person;
use App\Models\PointEntry;
use App\Models\RewardRedemption;
use App\Models\TaskCompletion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The points ledger.
 *
 * Entries are appended, never changed. The balance is the sum — a counter
 * field would lose the reason for every entry and, with parallel ticking on
 * two devices, entries themselves.
 */
final class PointLedger
{
    /**
     * Credit for a completed task.
     *
     * Without an assigned person no entry is made: points need a recipient,
     * and "somebody ticked it" is none. The task still counts as done — that
     * is the more common case, for instance when a parent taps in passing.
     */
    public function awardForCompletion(TaskCompletion $completion): ?PointEntry
    {
        $task = $completion->task;

        if ($completion->person_id === null || $task === null || $task->points <= 0) {
            return null;
        }

        return PointEntry::create([
            'person_id' => $completion->person_id,
            'points' => $task->points,
            'reason' => $task->title,
            'task_completion_id' => $completion->id,
        ]);
    }

    /**
     * Approves a redemption — debit and record in one go.
     *
     * In a transaction, because an approved redemption without a debit would
     * be a gift and a debit without approval a theft.
     *
     * The pending check and the balance belong in the same transaction as the
     * entry, behind a lock on the person: two parents approving at the same
     * time on the phone and at the wall would otherwise both see a pending
     * request and a sufficient balance. Locking the person rather than the
     * redemption also queues different wishes of the same child.
     *
     * @return 'approved'|'decided'|'insufficient'
     */
    public function approveRedemption(RewardRedemption $redemption, ?int $userId = null): string
    {
        return DB::transaction(function () use ($redemption, $userId): string {
            Person::query()->whereKey($redemption->person_id)->lockForUpdate()->first();

            $redemption->refresh();

            if (! $redemption->isPending()) {
                return 'decided';
            }

            $balance = (int) PointEntry::query()
                ->where('person_id', $redemption->person_id)
                ->sum('points');

            if ($balance < $redemption->cost) {
                return 'insufficient';
            }

            $redemption->forceFill([
                'approved_at' => Carbon::now(),
                'approved_by_user_id' => $userId,
            ])->save();

            $entry = PointEntry::create([
                'person_id' => $redemption->person_id,
                'points' => -$redemption->cost,
                'reason' => __('Redeemed: :reward', ['reward' => $redemption->reward?->name ?? __('Reward')]),
                'reward_redemption_id' => $redemption->id,
                'created_by_user_id' => $userId,
            ]);

            $entry->save();

            return 'approved';
        });
    }

    /**
     * A correction by a parent — an additional entry, never a change to an
     * existing one. That way the child can follow what happened when.
     */
    public function book(int $personId, int $points, string $reason, ?int $userId = null): PointEntry
    {
        return PointEntry::create([
            'person_id' => $personId,
            'points' => $points,
            'reason' => $reason,
            'created_by_user_id' => $userId,
        ]);
    }

    /**
     * Stars one child gives another, booked by a parent: taken and credited
     * in one go, or not at all.
     *
     * The giver is locked like in approveRedemption(): two parents giving
     * from the same child at once would otherwise both see enough stars.
     *
     * @return array{0: PointEntry, 1: PointEntry}|int the two entries, or the giver's balance when it is too small
     */
    public function gift(Person $from, Person $to, int $points, ?string $note, ?int $userId = null): array|int
    {
        return DB::transaction(function () use ($from, $to, $points, $note, $userId): array|int {
            Person::query()->whereKey($from->id)->lockForUpdate()->first();

            $balance = PointEntry::balanceFor((int) $from->id);

            if ($balance < $points) {
                return $balance;
            }

            $giftId = (string) Str::uuid();
            $suffix = $note !== null && $note !== '' ? ': '.$note : '';

            $taken = PointEntry::create([
                'person_id' => $from->id,
                'points' => -$points,
                'reason' => __('Gift to :name', ['name' => $to->name]).$suffix,
                'created_by_user_id' => $userId,
                'gift_id' => $giftId,
            ]);

            $credited = PointEntry::create([
                'person_id' => $to->id,
                'points' => $points,
                'reason' => __('Gift from :name', ['name' => $from->name]).$suffix,
                'created_by_user_id' => $userId,
                'gift_id' => $giftId,
            ]);

            return [$taken, $credited];
        });
    }

    /** @return array<int, int> balance per person ID */
    public function balances(): array
    {
        $balances = PointEntry::query()
            ->selectRaw('person_id, SUM(points) AS total')
            ->groupBy('person_id')
            ->pluck('total', 'person_id');

        return Person::query()
            ->pluck('id')
            ->mapWithKeys(fn (int $id): array => [$id => (int) ($balances[$id] ?? 0)])
            ->all();
    }
}
