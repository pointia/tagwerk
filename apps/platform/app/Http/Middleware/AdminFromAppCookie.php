<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\ActiveHousehold;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opens the admin for whoever is signed in on the phone, without a second
 * sign-in: the app's token stands for the web session.
 *
 * Only for an account the panel lets in anyway (`canAccessPanel`) — a child
 * signed in on the phone still lands on the login page. The session remembers
 * which token opened it; once that token is gone (signed out in the app,
 * password changed, account deleted) the admin session ends with it.
 *
 * Runs after the session has started and before Filament authenticates.
 */
final class AdminFromAppCookie
{
    public const SESSION = 'admin_from_app_token';

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        $session = $request->session();

        if ($guard->check()) {
            $opener = $session->get(self::SESSION);

            if ($opener !== null && PersonalAccessToken::query()->whereKey($opener)->doesntExist()) {
                $guard->logout();
                $session->invalidate();
                $session->regenerateToken();
            }

            return $next($request);
        }

        $access = $this->token($request);
        $user = $access?->tokenable;

        if ($access !== null && $user instanceof User && $user->canAccessPanel(Filament::getPanel('admin'))) {
            $guard->login($user);
            $session->regenerate();
            $session->put(self::SESSION, $access->getKey());

            // The household the app was looking at, if the account manages it;
            // ResolveHousehold checks that and otherwise picks one it does.
            if ($access->household_id !== null) {
                $session->put(ActiveHousehold::SESSION_KEY, (int) $access->household_id);
            }
        }

        return $next($request);
    }

    private function token(Request $request): ?PersonalAccessToken
    {
        $plain = $request->cookie(UserTokenFromCookie::COOKIE);

        if (! is_string($plain) || $plain === '') {
            return null;
        }

        $access = PersonalAccessToken::findToken($plain);

        if (! $access instanceof PersonalAccessToken || $access->expires_at?->isPast() === true) {
            return null;
        }

        return $access;
    }
}
