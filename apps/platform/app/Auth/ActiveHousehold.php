<?php

declare(strict_types=1);

namespace App\Auth;

use App\Enums\Permission;
use App\Models\Membership;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Support\Abilities;
use Illuminate\Http\Request;

/**
 * Which of its households a request of an account looks at.
 *
 * The choice sits on the token, not on the account: grandma may have one
 * family open on the phone and the other on the laptop. The admin keeps it
 * in its session and offers only households the account manages — a guest
 * at the Hubers is no administrator there. Whatever is stored only counts
 * as long as it is an accepted membership of this account, never a foreign
 * household.
 */
final class ActiveHousehold
{
    public const SESSION_KEY = 'household_id';

    public function resolve(User $user, ?Request $request = null): ?Membership
    {
        $admin = $this->isAdmin($user, $request);
        $wanted = $this->wanted($user, $request);
        $chosen = $wanted === null ? null : $user->membershipIn($wanted);

        if (! $this->usable($chosen, $admin)) {
            $fallback = $user->memberships()
                ->accepted()
                ->orderByDesc('updated_at')
                ->orderBy('id')
                ->get()
                ->first(fn (Membership $membership): bool => $this->usable($membership, $admin));

            // Nothing better: a suspended household still answers, with 403.
            $chosen = $fallback ?? ($admin ? null : $chosen ?? $user->defaultMembership());
        }

        if ($chosen !== null && $wanted !== (int) $chosen->household_id) {
            $this->remember($user, $request, (int) $chosen->household_id);
        }

        return $chosen;
    }

    /** Switches the token or the admin session to one of the account's households. */
    public function switch(User $user, ?Request $request, int $householdId): ?Membership
    {
        $membership = $user->membershipIn($householdId);

        if ($membership === null || ($this->isAdmin($user, $request) && ! $this->manages($membership))) {
            return null;
        }

        $this->remember($user, $request, $householdId);

        // The last one used is where a new sign-in starts.
        $membership->touch();

        return $membership;
    }

    /**
     * The households the admin may switch to.
     *
     * @return list<Membership>
     */
    public function managed(User $user): array
    {
        return $user->memberships()
            ->accepted()
            ->with('household:id,name,suspended_at')
            ->get()
            ->filter(fn (Membership $membership): bool => $this->manages($membership))
            ->sortBy(fn (Membership $membership): string => (string) $membership->household?->name)
            ->values()
            ->all();
    }

    private function usable(?Membership $membership, bool $admin): bool
    {
        return $membership !== null
            && $membership->household?->suspended_at === null
            && (! $admin || $this->manages($membership));
    }

    private function manages(Membership $membership): bool
    {
        return in_array(Permission::HouseholdManage, app(Abilities::class)->ofMembership($membership), true);
    }

    /** The admin signs in with the session, the app always with a token. */
    private function isAdmin(User $user, ?Request $request): bool
    {
        return ! $user->currentAccessToken() instanceof PersonalAccessToken
            && $request?->hasSession() === true;
    }

    private function wanted(User $user, ?Request $request): ?int
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            return $token->household_id === null ? null : (int) $token->household_id;
        }

        $id = $request?->hasSession() === true ? $request->session()->get(self::SESSION_KEY) : null;

        return is_int($id) ? $id : null;
    }

    private function remember(User $user, ?Request $request, int $householdId): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->forceFill(['household_id' => $householdId])->save();

            return;
        }

        if ($request?->hasSession() === true) {
            $request->session()->put(self::SESSION_KEY, $householdId);
        }
    }
}
