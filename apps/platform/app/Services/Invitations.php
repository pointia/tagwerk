<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\Invitation;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Accounts are invited, not handed a password.
 *
 * Until the link is used the account has a random password nobody knows and
 * no `email_verified_at` — that is what "invited" means in the admin panel.
 */
final class Invitations
{
    /** A password for the time until the invitation is accepted. */
    public static function placeholderPassword(): string
    {
        return Str::password(64);
    }

    public function send(User $user): void
    {
        $user->notify(new Invitation(Password::broker('invites')->createToken($user)));
    }

    public static function isPending(User $user): bool
    {
        return $user->email_verified_at === null;
    }
}
