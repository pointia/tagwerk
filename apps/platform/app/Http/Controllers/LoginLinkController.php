<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\SecondFactors;
use App\Services\LoginLinks;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The sign-in link for the admin panel, from the mail.
 */
final class LoginLinkController extends Controller
{
    public function __invoke(Request $request, LoginLinks $links): RedirectResponse
    {
        $user = $links->redeem((string) $request->query('email'), (string) $request->query('login'));
        $panel = Filament::getPanel('admin');

        if ($user === null || ! $user->canAccessPanel($panel)) {
            Notification::make()
                ->title(__('The sign-in link has expired or has already been used.'))
                ->danger()
                ->send();

            return redirect()->to($panel->getLoginUrl());
        }

        // A link replaces the password, not the second factor: Filament's
        // login page asks for both.
        $factors = app(SecondFactors::class);

        if ($factors->required($user) || $factors->missing($user)) {
            Notification::make()
                ->title(__('Please sign in with your password and the code from your authenticator app.'))
                ->warning()
                ->send();

            return redirect()->to($panel->getLoginUrl());
        }

        Auth::guard($panel->getAuthGuard())->login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended($panel->getUrl());
    }
}
