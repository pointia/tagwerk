<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Auth\ActiveHousehold;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\HouseholdContext;
use App\Support\Locale;
use App\Support\PackTexts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Which language the app starts in, before it shows a single text.
 *
 * Public: the pairing and sign-in screens need an answer too. A device or
 * account gets its chain (`Locale::for()`), nobody the best match of the
 * browser's languages among those installed — the server decides, so the
 * first text is already the right one.
 */
final class LanguageController extends Controller
{
    public function show(Request $request, ActiveHousehold $active, HouseholdContext $context): JsonResponse
    {
        $actor = Auth::guard('sanctum')->user();

        // No `household` middleware on a public route: an account's household
        // is resolved here, or its household's language would be missed.
        if ($actor instanceof User) {
            $context->setId(($active->resolve($actor, $request)?->household_id));
        }

        $code = $actor === null ? $this->fromBrowser($request) : Locale::for($actor);

        return response()->json(Locale::describe($code));
    }

    public function messages(string $code, string $hash, PackTexts $texts): JsonResponse
    {
        $served = $texts->served($code);

        abort_if($served === null || $served['hash'] !== $hash, 404);

        return response()
            ->json($served['messages'], options: JSON_UNESCAPED_UNICODE)
            ->header('Cache-Control', 'public, max-age=31536000, immutable');
    }

    /**
     * The first browser language whose primary subtag is installed —
     * `fr-CH` finds `fr`. Symfony's `getPreferredLanguage()` would answer
     * the first supported one when nothing matches, not the server default.
     */
    private function fromBrowser(Request $request): string
    {
        $supported = Locale::supported();

        foreach ($request->getLanguages() as $language) {
            $primary = strtolower(strtok($language, '_-') ?: '');

            if (array_key_exists($primary, $supported)) {
                return $primary;
            }
        }

        return (string) config('app.locale');
    }
}
