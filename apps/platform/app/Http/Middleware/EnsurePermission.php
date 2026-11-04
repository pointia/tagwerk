<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Support\Abilities;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `permission:lists.edit` on a route. Whether "own" fits the concrete record
 * is decided in the controller — the route cannot know it.
 */
final class EnsurePermission
{
    public function __construct(private readonly Abilities $abilities) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $this->abilities->authorize($request->user(), Permission::from($permission));

        return $next($request);
    }
}
