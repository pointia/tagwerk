<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Household;

/**
 * The time zone the family reads the clock in.
 *
 * Tables in the admin show points in time — a sync, a pairing, a support
 * access. Without this they show UTC, and "06:27" means half past eight in
 * Vienna in summer. Wall-clock times such as the bell schedule are not
 * points in time and never go through here.
 */
final class HouseholdTimezone
{
    public static function current(): string
    {
        return once(static fn (): string => Household::query()->find(app(HouseholdContext::class)->id())?->timezone
            ?? (string) config('app.timezone'));
    }
}
