<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Security\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The policy for everything Laravel answers — the admin above all. The app
 * of wall and phone is static and gets its header from nginx.
 */
final class SetContentSecurityPolicy
{
    public function __construct(private readonly ContentSecurityPolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->policy->mode() !== 'off') {
            $response->headers->set($this->policy->headerName(), $this->policy->forAdmin());
        }

        return $response;
    }
}
