<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Whether this server sends mail at all (`TAGWERK_MAIL`).
 */
final class OutgoingMail
{
    public static function enabled(): bool
    {
        return config('tagwerk.mail') !== 'off';
    }
}
