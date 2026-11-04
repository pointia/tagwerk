<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PersonRole;
use App\Models\Household;
use App\Models\Person;
use App\Models\User;
use App\Support\HouseholdContext;
use App\Support\Locale;
use Illuminate\Support\Facades\DB;

/**
 * A new household with its first parent.
 *
 * The parent gets a person right away, so they show up at the wall and can
 * create the accounts of the others in the admin panel. `HouseholdStarter`
 * fills in first tasks, rewards and a list. Without a password
 * they are invited by mail and set one themselves.
 */
final class HouseholdOnboarding
{
    public function __construct(
        private readonly HouseholdContext $context,
        private readonly Invitations $invitations,
        private readonly HouseholdStarter $starter,
    ) {}

    public function create(
        string $householdName,
        string $timezone,
        string $parentName,
        string $email,
        ?string $password = null,
        ?string $locale = null,
    ): User {
        $parent = DB::transaction(function () use ($householdName, $timezone, $parentName, $email, $password, $locale): User {
            $household = Household::create([
                'name' => $householdName,
                'timezone' => $timezone,
                'locale' => $locale ?? config('app.locale'),
            ]);

            return $this->context->forId((int) $household->id, function () use ($household, $timezone, $parentName, $email, $password): User {
                $person = Person::create([
                    'name' => $parentName,
                    'color' => '#4f46e5',
                    'role' => PersonRole::Adult,
                ]);

                $user = User::create([
                    'name' => $parentName,
                    'email' => $email,
                    'password' => $password ?? Invitations::placeholderPassword(),
                ]);
                $user->memberships()->create(['household_id' => $household->id, 'person_id' => $person->id, 'accepted_at' => now()]);

                // Whoever sets the password here vouches for the address.
                if ($password !== null) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }

                // Stored data, so in the household's language, not the operator's.
                Locale::using($household->locale, fn () => $this->starter->fill($person, $timezone));

                return $user;
            });
        });

        // After the commit: the queued mail must find the account.
        if ($password === null) {
            $this->invitations->send($parent);
        }

        return $parent;
    }
}
