<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Permission;
use App\Models\Device;
use App\Models\Household;
use App\Models\Membership;
use App\Models\Person;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Notifications\HouseholdInvitation;
use App\Support\Abilities;
use App\Support\OutgoingMail;
use Illuminate\Support\Facades\DB;

/**
 * Joining and leaving households.
 *
 * Whoever invites learns nothing about the address: a new account, a known
 * one and the operator's look the same from the inviting household. Joining
 * needs the account's consent — nobody turns up in a foreign household, and
 * with push in its notifications, without saying yes.
 */
final class Memberships
{
    public function __construct(
        private readonly Invitations $invitations,
        private readonly Abilities $abilities,
    ) {}

    /**
     * Invites an address as one of the household's people.
     *
     * @return Membership|null null when nothing was created (the operator's address)
     */
    public function invite(Household $household, Person $person, string $email, ?string $password = null): ?Membership
    {
        $known = User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();

        if ($known === null) {
            return $this->createAccount($household, $person, $email, $password);
        }

        if ($known->is_operator === true) {
            return null;
        }

        $membership = $known->memberships()->create([
            'household_id' => $household->id,
            'person_id' => $person->id,
        ]);

        if (OutgoingMail::enabled()) {
            $known->notify(new HouseholdInvitation((int) $household->id));
        }

        return $membership;
    }

    /** Sends the invitation again: the password link for a new account, otherwise the call to join. */
    public function resend(Membership $membership): void
    {
        $user = $membership->user;

        if ($user === null) {
            return;
        }

        if (Invitations::isPending($user)) {
            $this->invitations->send($user);
        } elseif ($membership->accepted_at === null) {
            $user->notify(new HouseholdInvitation((int) $membership->household_id));
        }
    }

    public function accept(User $user, int $householdId): bool
    {
        return $user->memberships()
            ->whereNull('accepted_at')
            ->where('household_id', $householdId)
            ->update(['accepted_at' => now(), 'updated_at' => now()]) > 0;
    }

    /**
     * Still waiting: a known account has not said yes, or a new one has not
     * set its password.
     */
    public static function isPending(Membership $membership): bool
    {
        return $membership->accepted_at === null
            || ($membership->user !== null && Invitations::isPending($membership->user));
    }

    /**
     * Ends a membership; the person stays in the household with its history.
     * An account left without any household is deleted — it could do nothing.
     */
    public function remove(Membership $membership): void
    {
        $user = $membership->user;

        DB::transaction(function () use ($membership, $user): void {
            Device::withoutHouseholdScope()
                ->where('household_id', $membership->household_id)
                ->where('user_id', $membership->user_id)
                ->delete();

            PersonalAccessToken::query()
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $membership->user_id)
                ->where('household_id', $membership->household_id)
                ->update(['household_id' => null]);

            $membership->delete();

            if ($user === null) {
                return;
            }

            if (! $user->memberships()->exists()) {
                $user->delete();
            }
        });
    }

    /** Whether the household would be left without anyone to manage it. */
    public function isLastManager(Membership $membership): bool
    {
        if ($membership->accepted_at === null || ! in_array(Permission::HouseholdManage, $this->abilities->ofMembership($membership), true)) {
            return false;
        }

        return Membership::query()
            ->accepted()
            ->where('household_id', $membership->household_id)
            ->whereKeyNot($membership->getKey())
            ->get()
            ->doesntContain(fn (Membership $other): bool => in_array(Permission::HouseholdManage, $this->abilities->ofMembership($other), true));
    }

    private function createAccount(Household $household, Person $person, string $email, ?string $password): Membership
    {
        $user = User::create([
            'name' => $person->name,
            'email' => trim($email),
            'password' => $password ?? Invitations::placeholderPassword(),
        ]);
        $membership = $user->memberships()->create(['household_id' => $household->id, 'person_id' => $person->id, 'accepted_at' => now()]);

        // A new account joins at once: nobody can use it before choosing a
        // password, and that is the consent.
        if ($password !== null) {
            // Whoever sets the password here vouches for the address.
            $user->forceFill(['email_verified_at' => now()])->save();
        } elseif (OutgoingMail::enabled()) {
            $this->invitations->send($user);
        }

        return $membership;
    }
}
