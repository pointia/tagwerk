<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Who is using the app: a paired device or a signed-in account.
 *
 * Wall and phone are one app under `/`; this is the one question it asks at
 * start to choose its shell. Both cookies may be present — the device wins
 * (see `DeviceTokenFromCookie`). Without either the route answers 401.
 */
final class SessionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'kind' => $request->user() instanceof Device ? 'device' : 'user',
        ]);
    }
}
