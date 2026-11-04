<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "Something changed in area X."
 *
 * Deliberately without content. An event carrying the changed data would
 * have to know every representation the wall and the phone use — and grow
 * with every change to a view. So it stays a nudge: the frontend reloads the
 * affected area with exactly the request it already has.
 *
 * That also keeps the channel harmless: no data flows, only hints.
 *
 * ShouldBroadcastNow instead of ShouldBroadcast: sending already happens in
 * a job of its own (AnnounceHouseholdUpdateJob) that catches failures. With
 * ShouldBroadcast a second queue layer would sit underneath — and with the
 * sync driver an unreachable Reverb would break the response, although the
 * change is long saved.
 */
final class HouseholdUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public const AREAS = ['agenda', 'tasks', 'lists', 'points', 'meals'];

    public function __construct(
        public readonly int $householdId,
        public readonly string $area,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("household.{$this->householdId}")];
    }

    public function broadcastAs(): string
    {
        return 'updated';
    }

    /** @return array<string, string> */
    public function broadcastWith(): array
    {
        return ['area' => $this->area];
    }
}
