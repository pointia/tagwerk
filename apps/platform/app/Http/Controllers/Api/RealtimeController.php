<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\HouseholdContext;
use Illuminate\Http\JsonResponse;

/**
 * Connection details for the WebSocket.
 *
 * Through the API instead of build-time variables: otherwise every frontend
 * would need a rebuild when host or key change — and the wall hangs on a
 * wall, not on the build server.
 *
 * The key is public; it names the app, it grants nothing. Who may join which
 * channel is decided by /broadcasting/auth.
 */
final class RealtimeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $key = (string) config('reverb.apps.apps.0.key');

        return response()->json([
            'enabled' => config('broadcasting.default') === 'reverb' && $key !== '',
            'key' => $key,
            'host' => config('reverb.apps.apps.0.options.host'),
            'port' => (int) config('reverb.apps.apps.0.options.port'),
            'scheme' => config('reverb.apps.apps.0.options.scheme'),
            // The household this request looks at: an account may belong to several.
            'channel' => 'household.'.app(HouseholdContext::class)->id(),
        ]);
    }
}
