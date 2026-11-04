<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Designs;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A design's stylesheet and the fonts and pictures it names.
 *
 * Public, like the language texts: the sign-in screen is drawn in the
 * design too, and nothing in it belongs to a household. The hash is in
 * the address, so both are cached for good.
 */
final class DesignController extends Controller
{
    private const CACHE = 'public, max-age=31536000, immutable';

    public function stylesheet(string $vendor, string $name, string $hash, Designs $designs): Response
    {
        $design = $designs->get("{$vendor}/{$name}");

        abort_if($design === null || $design['hash'] !== $hash, 404);

        return response($design['css'], 200, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => self::CACHE,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function asset(string $vendor, string $name, string $hash, string $file, Designs $designs): BinaryFileResponse
    {
        $design = $designs->get("{$vendor}/{$name}");

        // Only files the stylesheet names, checked when it was read.
        abort_if($design === null || $design['hash'] !== $hash || ! isset($design['assets'][$file]), 404);

        return response()->file($design['assets'][$file], [
            'Cache-Control' => self::CACHE,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
