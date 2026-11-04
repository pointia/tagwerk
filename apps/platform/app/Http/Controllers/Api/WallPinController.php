<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\User;
use App\Services\ParentPin;
use App\Support\Abilities;
use App\Support\HouseholdContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Unlocks the parent-only actions.
 *
 * The route is throttled. A four-digit PIN without a limit on attempts is no
 * protection — ten thousand combinations are tried in seconds.
 */
final class WallPinController extends Controller
{
    public function __construct(
        private readonly ParentPin $pin,
        private readonly HouseholdContext $context,
        private readonly Abilities $abilities,
    ) {}

    public function show(Request $request): JsonResponse
    {
        // The PIN stands in for a sign-in on the wall; an account has its own
        // permissions and is never asked.
        if ($request->user() instanceof User) {
            return response()->json(['required' => false, 'unlocked' => true]);
        }

        $household = $this->household();

        return response()->json([
            'required' => $household->hasPin(),
            'unlocked' => $this->pin->isUnlocked($household, (string) $request->user()?->getKey()),
        ]);
    }

    public function unlock(Request $request): JsonResponse
    {
        $data = $request->validate(['pin' => ['required', 'string', 'min:4', 'max:12']]);

        $household = $this->household();

        if (! $this->pin->unlock($household, (string) $request->user()?->getKey(), $data['pin'])) {
            throw ValidationException::withMessages(['pin' => __('The PIN is wrong.')]);
        }

        $this->abilities->forget();

        return response()->json([
            'unlocked' => true,
            'seconds' => $this->pin->unlockedUntil(),
        ]);
    }

    /** Locks explicitly, without waiting for the expiry. */
    public function lock(Request $request): JsonResponse
    {
        $this->pin->lock((string) $request->user()?->getKey());
        $this->abilities->forget();

        return response()->json(status: 204);
    }

    private function household(): Household
    {
        return Household::query()->findOrFail($this->context->id());
    }
}
