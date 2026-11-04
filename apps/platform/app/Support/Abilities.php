<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Permission;
use App\Enums\PersonRole;
use App\Exceptions\PermissionDenied;
use App\Models\Device;
use App\Models\Household;
use App\Models\Membership;
use App\Models\User;
use App\Services\ParentPin;

/**
 * What an actor may change: a signed-in account (phone, admin panel) or a
 * wall device. Everything that writes asks here, never the interface.
 *
 * Bound per request: the agenda asks once per event.
 */
final class Abilities
{
    /** @var array<string, list<Permission>> */
    private array $granted = [];

    /** @var array<string, int|null> */
    private array $people = [];

    public function __construct(
        private readonly ParentPin $pin,
        private readonly HouseholdContext $context,
    ) {}

    /** @return list<Permission> */
    public function of(User|Device|null $actor): array
    {
        if ($actor === null) {
            return [];
        }

        // The household is part of the key: the operator's rights change when
        // stepping into a household to support it.
        return $this->granted[$actor::class.':'.$actor->getKey().'@'.$this->context->id()] ??= match (true) {
            $actor instanceof User => $this->ofUser($actor),
            $actor instanceof Device => $this->ofDevice($actor),
        };
    }

    /** After the PIN was entered or locked again, the wall's rights change. */
    public function forget(): void
    {
        $this->granted = [];
        $this->people = [];
    }

    public function allows(User|Device|null $actor, Permission $permission): bool
    {
        $granted = $this->of($actor);
        $wider = $permission->widerPermission();

        return in_array($permission, $granted, true)
            || ($wider !== null && in_array($wider, $granted, true));
    }

    public function authorize(User|Device|null $actor, Permission $permission): void
    {
        if (! $this->allows($actor, $permission)) {
            throw new PermissionDenied(pinHelps: $actor instanceof Device);
        }
    }

    /**
     * May the actor act on something that concerns these people?
     *
     * With the "all" permission always. With "own" only if the actor's
     * person is among them — nobody at all means the whole family.
     *
     * @param  array<int, int|null>  $people
     */
    public function mayActOn(User|Device|null $actor, Permission $own, array $people): bool
    {
        $wider = $own->widerPermission();

        if ($wider !== null && $this->allows($actor, $wider)) {
            return true;
        }

        $person = $this->personOf($actor);

        return $person !== null
            && in_array($own, $this->of($actor), true)
            && in_array($person, $people, true);
    }

    /** @param  array<int, int|null>  $people */
    public function authorizeOn(User|Device|null $actor, Permission $own, array $people): void
    {
        if (! $this->mayActOn($actor, $own, $people)) {
            throw new PermissionDenied(pinHelps: $actor instanceof Device);
        }
    }

    /**
     * Ticking a task for its assignee. A task for nobody ("Für alle") counts
     * as one's own: setting the table is something a child may tick.
     */
    public function mayComplete(User|Device|null $actor, ?int $assignee): bool
    {
        return $assignee === null && in_array(Permission::TasksCompleteOwn, $this->of($actor), true) && $this->personOf($actor) !== null
            || $this->mayActOn($actor, Permission::TasksCompleteOwn, [$assignee]);
    }

    public function authorizeCompletion(User|Device|null $actor, ?int $assignee): void
    {
        if (! $this->mayComplete($actor, $assignee)) {
            throw new PermissionDenied(pinHelps: $actor instanceof Device);
        }
    }

    /**
     * The person behind the actor in the household of the request; a wall
     * device has none.
     */
    public function personOf(User|Device|null $actor): ?int
    {
        if (! $actor instanceof User) {
            return null;
        }

        $key = $actor->getKey().'@'.$this->context->id();

        if (! array_key_exists($key, $this->people)) {
            $this->people[$key] = $actor->currentPersonId();
        }

        return $this->people[$key];
    }

    /**
     * What an account may do in one household, whatever the request looks
     * at — the wall's PIN asks for the wall's household.
     *
     * @return list<Permission>
     */
    public function ofIn(User $user, int $householdId): array
    {
        $membership = $user->membershipIn($householdId);

        return $membership === null ? [] : $this->ofMembership($membership);
    }

    /** @return list<Permission> */
    public function ofMembership(Membership $membership): array
    {
        // The account the household was set up with, before any person existed.
        if ($membership->person_id === null) {
            return Permission::cases();
        }

        $person = $membership->person;

        if ($person === null) {
            return [];
        }

        return $person->permissions !== null
            ? Permission::fromStored($person->permissions)
            : Permission::defaultsFor($person->role ?? PersonRole::Child);
    }

    /** @return list<Permission> */
    private function ofUser(User $user): array
    {
        $membership = $user->membership();

        if ($membership !== null) {
            return $this->ofMembership($membership);
        }

        // The operator belongs to no household and manages all of them, also
        // inside one they support.
        return $user->is_operator === true ? Permission::cases() : [];
    }

    /** @return list<Permission> */
    private function ofDevice(Device $device): array
    {
        $household = Household::query()->find($device->household_id);

        if ($household !== null && $this->pin->isUnlocked($household, $device->getKey())) {
            return Permission::cases();
        }

        return $device->permissions !== null
            ? Permission::fromStored($device->permissions)
            : Permission::deviceDefaults();
    }
}
