<?php

declare(strict_types=1);

namespace App\Auth;

use App\Http\Middleware\UserTokenFromCookie;
use App\Models\User;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The app's sign-in for an account: a Sanctum token in an httpOnly cookie,
 * never within reach of JavaScript. Password, mail link and plugins such as
 * a social sign-in hand out the same one.
 */
final class AppToken
{
    /** Half a year: long enough that nobody has to sign in all the time. */
    private const DAYS = 180;

    public function issue(User $user): Cookie
    {
        $token = $user->createToken('mobile', ['*'], now()->addDays(self::DAYS));

        return cookie(
            name: UserTokenFromCookie::COOKIE,
            value: $token->plainTextToken,
            minutes: self::DAYS * 24 * 60,
            path: '/',
            secure: true,
            httpOnly: true,
            sameSite: 'lax',
        );
    }
}
