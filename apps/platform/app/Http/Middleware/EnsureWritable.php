<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Household;
use App\Services\HouseholdNotices;
use App\Support\HouseholdContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A read-only household reads everything and writes nothing: 402 on every
 * unsafe request of a route that carries the household.
 *
 * In the api group for every route, so a new one cannot forget it; routes
 * without `household` have no household to ask. What must keep working
 * while read-only — signing in and out, the PIN, push, preferences —
 * carries the action `allows_read_only` (`EnsureWritable::SELF`).
 */
final class EnsureWritable
{
    public const SELF = 'allows_read_only';

    public function __construct(
        private readonly HouseholdContext $context,
        private readonly HouseholdNotices $notices,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if ($request->isMethodSafe()
            || ! $this->context->hasId()
            || ! in_array('household', $route?->gatherMiddleware() ?? [], true)
            || $route?->getAction(self::SELF) === true) {
            return $next($request);
        }

        $readOnly = Household::query()->whereKey($this->context->id())->whereNotNull('read_only_since')->exists();

        if (! $readOnly) {
            return $next($request);
        }

        $notice = $this->notices->blocking((int) $this->context->id());

        return response()->json([
            'message' => $notice?->text ?? __('This household is read-only.'),
            'url' => $notice?->url,
            'action' => $notice?->url === null ? null : $notice->action,
        ], 402);
    }
}
