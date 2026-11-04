<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Device;
use App\Models\User;
use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the language of whoever asks.
 *
 * Reset to the default first: the application is a singleton in long-lived
 * processes, and a request without a user must not inherit the previous one's.
 */
final class ApplyLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();

        App::setLocale(Locale::for($actor instanceof User || $actor instanceof Device ? $actor : null));

        return $next($request);
    }
}
