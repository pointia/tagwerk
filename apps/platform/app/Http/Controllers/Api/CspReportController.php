<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Where browsers report what the policy blocked (or, report-only, would
 * have blocked). Public — a report comes from whatever page was open — so
 * only a few known fields are kept, each cut short.
 */
final class CspReportController extends Controller
{
    private const FIELDS = ['document-uri', 'effective-directive', 'violated-directive', 'blocked-uri', 'source-file', 'line-number'];

    public function __invoke(Request $request): Response
    {
        // Browsers send `application/csp-report`, which Laravel does not parse.
        $decoded = json_decode(mb_substr($request->getContent(), 0, 8192), true);
        $report = is_array($decoded) ? ($decoded['csp-report'] ?? null) : null;

        if (is_array($report)) {
            Log::warning('Content security policy violation', array_map(
                fn (mixed $value): string => mb_substr(is_scalar($value) ? (string) $value : '', 0, 300),
                array_intersect_key($report, array_flip(self::FIELDS)),
            ));
        }

        return response()->noContent();
    }
}
