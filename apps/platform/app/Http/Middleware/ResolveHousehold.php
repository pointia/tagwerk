<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\ActiveHousehold;
use App\Models\Household;
use App\Models\User;
use App\Services\SupportAccessService;
use App\Support\HouseholdContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fills the household context from the authenticated model.
 *
 * A device carries its household; an account looks at one of its
 * memberships (`ActiveHousehold`). A suspended household gets 403 on every
 * route that carries this middleware — its data stays untouched.
 */
final class ResolveHousehold
{
    public function __construct(
        private readonly HouseholdContext $context,
        private readonly SupportAccessService $support,
        private readonly ActiveHousehold $active,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $authenticated = $request->user();

        // Explicitly empty first: the context is a singleton, and an operator
        // without a household must not inherit one from an earlier request.
        $this->context->setId(null);

        // The operator helping a household, in the admin panel only. Suspended
        // households stay reachable: support is often why they are suspended.
        $supported = $authenticated instanceof User ? $this->support->current($authenticated) : null;

        if ($supported !== null && Household::query()->whereKey($supported)->exists()) {
            $this->context->setId($supported);

            return $next($request);
        }

        if ($authenticated instanceof User) {
            $membership = $this->active->resolve($authenticated, $request);

            if ($membership !== null) {
                abort_if($membership->household?->suspended_at !== null, 403);

                $this->context->setId((int) $membership->household_id);
            }

            return $next($request);
        }

        if ($authenticated?->household_id !== null) {
            abort_if(Household::query()->whereKey($authenticated->household_id)->whereNotNull('suspended_at')->exists(), 403);

            $this->context->setId((int) $authenticated->household_id);
        }

        return $next($request);
    }
}
