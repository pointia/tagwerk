<?php

declare(strict_types=1);

namespace App\Auth;

use App\Http\Middleware\UserTokenFromCookie;
use App\Models\PersonalAccessToken;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Auth\Http\Responses\LogoutResponse as FilamentLogoutResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Signing out in the admin signs this browser out of the app as well.
 *
 * `AdminFromAppCookie` opens the admin from the app's cookie; left in place,
 * the cookie signed the admin straight back in on the way to the login page.
 * A password session without the cookie keeps Filament's way out.
 */
final class AdminLogoutResponse implements LogoutResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $plain = $request->cookie(UserTokenFromCookie::COOKIE);

        if (! is_string($plain) || $plain === '') {
            return (new FilamentLogoutResponse)->toResponse($request);
        }

        PersonalAccessToken::findToken($plain)?->delete();

        return redirect('/')->withCookie(Cookie::forget(UserTokenFromCookie::COOKIE));
    }
}
