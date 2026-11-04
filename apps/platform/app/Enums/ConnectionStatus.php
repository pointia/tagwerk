<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether a connection to a provider still works.
 */
enum ConnectionStatus: string
{
    case Active = 'active';

    /**
     * The provider discarded the refresh token. Not an incident but an
     * expected state: tokens of unverified apps expire, and the account
     * owner may revoke them.
     */
    case InvalidGrant = 'invalid_grant';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Connected'),
            self::InvalidGrant => __('Sign in again'),
        };
    }
}
