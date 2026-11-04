<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DeviceKind;
use App\Http\Controllers\Controller;
use App\Jobs\ConfirmPushJob;
use App\Models\Device;
use App\Models\User;
use App\Services\Push\WebPushService;
use App\Support\HouseholdContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * A phone's push subscription.
 *
 * The device row is created here and only carries the subscription;
 * authentication goes through the personal login, never through it.
 */
final class PushSubscriptionController extends Controller
{
    public function __construct(
        private readonly WebPushService $push,
        private readonly HouseholdContext $context,
    ) {}

    /** The public key the browser needs to subscribe. */
    public function key(): JsonResponse
    {
        return response()->json([
            'configured' => $this->push->isConfigured(),
            'public_key' => config('services.webpush.public_key'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:100'],
            'confirm' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => __('Only with a personal sign-in.')], 403);
        }

        /*
         * Endpoint and household are the key, not the user: the same phone
         * subscribes again with the same endpoint after a browser restart, and
         * two rows for it would send every notification twice. One row per
         * household the account belongs to, so each family reaches the phone
         * whichever one it looks at.
         */
        $device = null;

        foreach ($user->memberships()->accepted()->get() as $membership) {
            $row = Device::withoutHouseholdScope()->firstOrNew([
                'push_endpoint' => $data['endpoint'],
                'household_id' => $membership->household_id,
            ]);

            $row->forceFill([
                'household_id' => $membership->household_id,
                'user_id' => $user->id,
                'kind' => DeviceKind::Mobile,
                'name' => $data['name'] ?? $user->name,
                'push_endpoint' => $data['endpoint'],
                'push_p256dh' => $data['keys']['p256dh'],
                'push_auth' => $data['keys']['auth'],
                'push_subscribed_at' => Carbon::now(),
            ])->save();

            if ((int) $membership->household_id === $this->context->id() || $device === null) {
                $device = $row;
            }
        }

        if ($device === null) {
            return response()->json(['message' => __('This account belongs to no household.')], 403);
        }

        // Only when someone just switched it on: the phone also re-registers
        // on every start, and a sample each time would be noise.
        if ($request->boolean('confirm')) {
            ConfirmPushJob::dispatch((int) $device->id);
        }

        return response()->json(['id' => $device->id, 'subscribed' => true], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);

        // Every household's row of this phone: switched off is off everywhere.
        Device::withoutHouseholdScope()
            ->where('push_endpoint', $data['endpoint'])
            ->where('user_id', $request->user()?->getKey())
            ->get()
            ->each(fn (Device $device) => $device->forceFill([
                'push_endpoint' => null,
                'push_p256dh' => null,
                'push_auth' => null,
                'push_subscribed_at' => null,
            ])->save());

        return response()->json(status: 204);
    }
}
