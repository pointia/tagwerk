<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasZonedTimestamps;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A stored message to an account (`notifications`), with the household it
 * is about. Laravel's model, only with zoned timestamps: the columns are
 * `timestampTz`.
 */
final class Notification extends DatabaseNotification
{
    use HasZonedTimestamps;
}
