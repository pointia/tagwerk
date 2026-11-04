<?php

declare(strict_types=1);

namespace App\Auth\Contracts;

use App\Models\User;

/**
 * An extension that asks for a second factor at sign-in.
 *
 * Filament's admin login asks by itself; the phone's sign-in and the login
 * links ask through App\Auth\SecondFactors, which consults every active
 * extension implementing this.
 */
interface SecondFactor
{
    /** The account has a factor set up and is asked for it. */
    public function isSetUp(User $user): bool;

    /** The account must have one but has none yet. */
    public function isMissing(User $user): bool;

    public function verify(User $user, #[\SensitiveParameter] string $code): bool;
}
