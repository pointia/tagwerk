<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What happens to a lesson on a given day.
 *
 * Both stay visible: a cancelled lesson does not disappear, it is struck
 * through. Otherwise nobody at the wall tablet would notice that the day
 * runs differently than usual.
 */
enum LessonChangeKind: string
{
    case Cancelled = 'cancelled';
    case Replaced = 'replaced';

    public function label(): string
    {
        return match ($this) {
            self::Cancelled => __('cancelled'),
            self::Replaced => __('instead'),
        };
    }
}
