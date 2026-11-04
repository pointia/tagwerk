<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Household;
use App\Models\SupportAccess;
use App\Models\User;

/**
 * The operator steps into a household to help — and back out.
 *
 * The choice lives in the session, so it only applies in the admin panel:
 * phone and wall authenticate by token and never see it.
 */
final class SupportAccessService
{
    public const SESSION_KEY = 'support_household_id';

    public function enter(User $operator, Household $household): void
    {
        abort_unless($operator->is_operator === true, 403);

        if ($operator->membershipIn((int) $household->id) !== null) {
            $this->leave();

            return;
        }

        session()->put(self::SESSION_KEY, $household->id);
        SupportAccess::query()->create(['user_id' => $operator->id, 'household_id' => $household->id]);
    }

    public function leave(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /** The household being supported, or null in one's own. */
    public function current(?User $user): ?int
    {
        if ($user?->is_operator !== true || ! request()->hasSession()) {
            return null;
        }

        $id = request()->session()->get(self::SESSION_KEY);

        return is_int($id) ? $id : null;
    }
}
