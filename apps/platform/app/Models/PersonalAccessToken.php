<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Resolves a token's owner without the household scope.
 *
 * Necessary because the scope takes its value from the authenticated model:
 * while the token is resolved, the context is necessarily still empty.
 * Without this exception a device could never sign in.
 */
final class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /** @return MorphTo<Model, $this> */
    public function tokenable(): MorphTo
    {
        return $this->morphTo('tokenable')->withoutGlobalScope('household');
    }
}
