<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Services\LoginLinks;
use App\Support\OutgoingMail;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

/**
 * Filament's sign-in, plus a link by mail instead of the password.
 */
class Login extends BaseLogin
{
    /** @return array<Action> */
    protected function getFormActions(): array
    {
        return [
            ...parent::getFormActions(),
            Action::make('sendLoginLink')
                ->label(__('Sign-in link by e-mail instead'))
                ->link()
                ->visible(fn (): bool => OutgoingMail::enabled())
                ->action(fn () => $this->sendLoginLink()),
        ];
    }

    public function sendLoginLink(): void
    {
        abort_unless(OutgoingMail::enabled(), 404);

        $email = (string) ($this->data['email'] ?? '');

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->addError('data.email', __('Please enter the e-mail address first.'));

            return;
        }

        // Same key as the API: email and address together.
        $key = 'login-link:'.mb_strtolower($email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            Notification::make()->title(__('Too many attempts. Please wait a moment.'))->danger()->send();

            return;
        }

        RateLimiter::hit($key);
        app(LoginLinks::class)->issue($email, 'admin');

        // The same answer for every address.
        Notification::make()
            ->title(__('If there is an account for this address, the link is on its way.'))
            ->success()
            ->send();
    }
}
