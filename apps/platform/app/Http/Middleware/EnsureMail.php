<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\OutgoingMail;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the routes that send or redeem a mail when the server sends none.
 * 404 like a disabled area: on this server the way does not exist.
 */
final class EnsureMail
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(OutgoingMail::enabled(), 404);

        return $next($request);
    }
}
