<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Household;
use App\Support\HouseholdContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the routes of a disabled area.
 *
 * Without this check "off" would only be a display matter: a tablet with a
 * stale navigation would keep writing, and anyone knowing the URL would get
 * through anyway.
 *
 * Responds with 404, not 403: for this household the area does not exist —
 * this is not a denied permission but something that is not there.
 */
final class EnsureFeature
{
    public function __construct(private readonly HouseholdContext $context) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $household = Household::query()->findOrFail($this->context->id());

        abort_unless($household->has($feature), 404);

        return $next($request);
    }
}
