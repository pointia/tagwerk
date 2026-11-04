<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\HouseholdUpdated;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Announces a change to the household's devices.
 *
 * Failures are caught: an unreachable Reverb costs the immediate refresh,
 * not the change itself. Wall and phone poll regularly anyway — the
 * announcement only shortens the wait from minutes to moments.
 *
 * That must not depend on the queue driver: with `sync` the broadcast would
 * otherwise run inside the request and take a saved change down with it.
 *
 * One announcement per household and area at a time: a sync saves hundreds
 * of rows, and every open wall and phone fetched everything again per row —
 * until the browser ran out of connections. The lock holds from dispatch
 * until the job starts; whatever is saved later announces itself again, so
 * the last change is never swallowed.
 */
final class AnnounceHouseholdUpdateJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    /** Released in any case after this long, should a job get lost. */
    public int $uniqueFor = 60;

    public function __construct(
        public readonly int $householdId,
        public readonly string $area,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->householdId}:{$this->area}";
    }

    public function handle(): void
    {
        try {
            broadcast(new HouseholdUpdated($this->householdId, $this->area));
        } catch (Throwable $exception) {
            Log::warning('Could not announce household update.', [
                'household_id' => $this->householdId,
                'area' => $this->area,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
