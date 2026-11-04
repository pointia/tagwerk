<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tokens for other programs — Home Assistant, a script, an AI assistant —
 * that act as the account, in one household, read-only or with writing.
 *
 * An ordinary Sanctum token with the ability `api` plus `read` and maybe
 * `write`; `ApiTokenScope` enforces them. The account's permissions in the
 * household apply on top, so a token can never do more than its account. A
 * password change revokes them like every other token (`User::booted`).
 */
final class ApiTokens
{
    public const ABILITY = 'api';

    public const READ = 'read';

    public const WRITE = 'write';

    public const PREFIX = 'api:';

    /** @return string the plain token — shown once, never stored */
    public function create(User $user, int $householdId, string $label, bool $write, ?CarbonImmutable $expiresAt = null): string
    {
        abort_unless($user->membershipIn($householdId) !== null, 403);

        $abilities = $write ? [self::ABILITY, self::READ, self::WRITE] : [self::ABILITY, self::READ];
        $token = $user->createToken(self::PREFIX.$label, $abilities, $expiresAt);
        $token->accessToken->forceFill(['household_id' => $householdId])->save();

        return $token->plainTextToken;
    }

    /** @return Builder<PersonalAccessToken> */
    public function of(User $user): Builder
    {
        return PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->where('name', 'like', self::PREFIX.'%');
    }

    /**
     * Whether a request's token is one of these. Checked on the list, not with
     * `can()`: an app token carries `*`, which `can()` answers yes to.
     */
    public static function isApiToken(mixed $token): bool
    {
        return $token instanceof PersonalAccessToken && in_array(self::ABILITY, (array) $token->abilities, true);
    }

    /** Whether an API token holds `read` or `write`; the list, not `can()` (see above). */
    public static function allows(mixed $token, string $ability): bool
    {
        return self::isApiToken($token) && in_array($ability, (array) $token->abilities, true);
    }
}
