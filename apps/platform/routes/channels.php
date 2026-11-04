<?php

declare(strict_types=1);

use App\Auth\ActiveHousehold;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/**
 * One channel per household.
 *
 * No channel per person: on the wall there is no person but a device, and
 * everyone sees the same anyway. What goes through here are exclusively
 * "area X has changed" hints — no content. That keeps the channel harmless
 * even when a child is listening at the tablet.
 *
 * Authorization applies to users and devices alike: both carry a
 * household_id, and both sign in through Sanctum.
 */
Broadcast::channel('household.{householdId}', function (object $angemeldet, string $householdId): bool {
    // An account hears only the household its token looks at.
    if ($angemeldet instanceof User) {
        $membership = app(ActiveHousehold::class)->resolve($angemeldet, request());

        return $membership !== null && (int) $membership->household_id === (int) $householdId;
    }

    return isset($angemeldet->household_id)
        && (int) $angemeldet->household_id === (int) $householdId;
});
