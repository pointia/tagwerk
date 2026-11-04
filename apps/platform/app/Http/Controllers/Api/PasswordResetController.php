<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Forgotten password and accepted invitation, from the app's sign-in
 * screen. Both end the same way: the owner of the address sets a password.
 *
 * The request always answers the same way: otherwise the form would tell
 * anyone which addresses have an account.
 */
final class PasswordResetController extends Controller
{
    public function request(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink(['email' => $data['email']]);

        return response()->json(status: 202);
    }

    public function reset(Request $request): JsonResponse
    {
        return $this->setPassword($request, 'users');
    }

    public function accept(Request $request): JsonResponse
    {
        return $this->setPassword($request, 'invites');
    }

    private function setPassword(Request $request, string $broker): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        // A new password revokes every token of the account (User::booted).
        $status = Password::broker($broker)->reset($data, function (User $user, string $password): void {
            $user->update(['password' => $password]);
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => __('The link has expired or has already been used. Please ask for a new one.'),
            ]);
        }

        return response()->json(status: 204);
    }
}
