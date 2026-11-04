<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\LoginLink;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Signing in without a password: a link, and a code for the installed app.
 *
 * On a phone the link opens in the browser, not in the app on the home
 * screen — they keep separate cookies. The code is typed into the app
 * instead. Both are good once and for LIFETIME_MINUTES; after MAX_ATTEMPTS
 * wrong codes the whole pair is void.
 *
 * Six characters from 32 instead of six digits: a billion combinations, so
 * even an attacker who keeps requesting new codes gets nowhere.
 */
final class LoginLinks
{
    public const LIFETIME_MINUTES = 15;

    private const MAX_ATTEMPTS = 5;

    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Where the link leads: the app or the admin panel. */
    public function issue(string $email, string $target): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return;
        }

        $token = Str::random(48);
        $code = '';

        for ($i = 0; $i < 6; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        Cache::put($this->key($email), [
            'user_id' => $user->id,
            'token' => $this->hash($token),
            'code' => $this->hash($code),
            'attempts' => 0,
        ], now()->addMinutes(self::LIFETIME_MINUTES));

        $query = http_build_query(['login' => $token, 'email' => $user->email]);
        $url = $target === 'admin' ? url('/login-link?'.$query) : url('/?'.$query);

        $user->notify(new LoginLink($url, $target === 'admin' ? null : $code));
    }

    /**
     * The account the link or code belongs to, or null. Used once: a
     * successful redemption voids both.
     */
    public function redeem(string $email, ?string $token = null, ?string $code = null): ?User
    {
        $key = $this->key($email);
        $entry = Cache::get($key);

        if (! is_array($entry)) {
            return null;
        }

        $matches = ($token !== null && $token !== '' && hash_equals($entry['token'], $this->hash($token)))
            || ($code !== null && $code !== '' && hash_equals($entry['code'], $this->hash(Str::upper(trim($code)))));

        if (! $matches) {
            $entry['attempts']++;

            $entry['attempts'] >= self::MAX_ATTEMPTS
                ? Cache::forget($key)
                : Cache::put($key, $entry, now()->addMinutes(self::LIFETIME_MINUTES));

            return null;
        }

        Cache::forget($key);

        $user = User::query()->find($entry['user_id']);

        // The address is proven: that also ends an invitation still open.
        if ($user !== null && $user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }

    private function key(string $email): string
    {
        return 'login-link:'.hash('sha256', Str::lower(trim($email)));
    }

    private function hash(string $secret): string
    {
        return hash_hmac('sha256', $secret, (string) config('app.key'));
    }
}
