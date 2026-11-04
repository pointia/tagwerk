<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Contracts\SecondFactor;
use App\Integrations\Plugins\Extensions;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The second step of a sign-in outside Filament's own login page.
 *
 * Password or link first; if an active extension wants a code, the account
 * is not signed in yet but gets a pending ticket (five minutes, five tries)
 * that only the code redeems. Without such an extension nothing changes.
 */
final class SecondFactors
{
    private const TTL_MINUTES = 5;

    private const ATTEMPTS = 5;

    public function __construct(private readonly Extensions $extensions) {}

    public function required(User $user): bool
    {
        return array_any($this->factors(), fn (SecondFactor $factor): bool => $factor->isSetUp($user));
    }

    public function missing(User $user): bool
    {
        return ! $this->required($user)
            && array_any($this->factors(), fn (SecondFactor $factor): bool => $factor->isMissing($user));
    }

    /** A ticket that the code redeems for the account. */
    public function pending(User $user): string
    {
        $ticket = Str::random(40);
        Cache::put($this->key($ticket), ['user' => $user->getKey(), 'attempts' => 0], now()->addMinutes(self::TTL_MINUTES));

        return $ticket;
    }

    /** The account once the code is right; null for a wrong code or a spent ticket. */
    public function redeem(string $ticket, #[\SensitiveParameter] string $code): ?User
    {
        $pending = Cache::get($this->key($ticket));
        $user = is_array($pending) ? User::query()->find($pending['user']) : null;

        if ($user === null) {
            return null;
        }

        if (array_any($this->factors(), fn (SecondFactor $factor): bool => $factor->isSetUp($user) && $factor->verify($user, $code))) {
            Cache::forget($this->key($ticket));

            return $user;
        }

        $pending['attempts']++;
        $pending['attempts'] >= self::ATTEMPTS
            ? Cache::forget($this->key($ticket))
            : Cache::put($this->key($ticket), $pending, now()->addMinutes(self::TTL_MINUTES));

        return null;
    }

    /** @return list<SecondFactor> */
    private function factors(): array
    {
        return array_values(array_filter($this->extensions->active(), fn (object $extension): bool => $extension instanceof SecondFactor));
    }

    private function key(string $ticket): string
    {
        return 'second-factor:'.hash('sha256', $ticket);
    }
}
