<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Areas\ApiArea;
use App\Auth\ApiTokens;
use App\Models\Household;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * A token for another program reads with `read` and writes with `write`.
 *
 * Every other token — app, device — passes untouched. Runs after
 * authentication (priority list); before it there is no token to look at.
 * 403, not 401: the token is valid, it just may not do this.
 *
 * A route whose method says nothing about reading or writing (an MCP
 * endpoint takes every call as POST) carries the action `checks_token_scope`
 * and asks `ApiTokens::allows()` itself.
 *
 * Before the scope: the household must have switched API access on (404
 * otherwise, as for any area that is off), and one token gets `PER_MINUTE`
 * requests — a leaked token cannot read the household out in a burst, and
 * a script in a loop does not hold up the wall. An MCP call counts twice:
 * once itself, once the API request it makes.
 */
final class ApiTokenScope
{
    public const SELF = 'checks_token_scope';

    public const PER_MINUTE = 120;

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (ApiTokens::isApiToken($token)) {
            $household = Household::query()->active()->find($token->household_id);
            abort_unless($household?->has(ApiArea::KEY) ?? false, 404);

            abort_if(RateLimiter::tooManyAttempts('api-token:'.$token->getKey(), self::PER_MINUTE), 429);
            RateLimiter::hit('api-token:'.$token->getKey());
        }

        if (ApiTokens::isApiToken($token) && $request->route()?->getAction(self::SELF) !== true) {
            $needed = $request->isMethodSafe() ? ApiTokens::READ : ApiTokens::WRITE;

            abort_unless(ApiTokens::allows($token, $needed), 403, __('This token may not do that.'));
        }

        return $next($request);
    }
}
