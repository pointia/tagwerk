<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\UserTokenFromCookie;
use App\Models\Household;
use App\Models\User;
use App\Services\AccountDeletion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/** Deleting one's own account, from the phone's settings. */
final class AccountController extends Controller
{
    /** What deleting would take along, for the screen before. */
    public function show(Request $request, AccountDeletion $deletion): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return response()->json([
            'households_lost' => array_map(
                static fn (Household $household): array => ['id' => (int) $household->id, 'name' => (string) $household->name],
                $deletion->householdsLost($user),
            ),
        ]);
    }

    public function destroy(Request $request, AccountDeletion $deletion): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $data = $request->validate([
            'password' => ['required', 'string'],
            'confirm' => ['present', 'array'],
            'confirm.*' => ['string'],
        ]);

        $deletion->delete($user, $data['password'], array_values($data['confirm']));

        return response()
            ->json(status: 204)
            ->withCookie(Cookie::forget(UserTokenFromCookie::COOKIE));
    }
}
