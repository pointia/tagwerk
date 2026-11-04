<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How the two-week rhythm of a lesson counts.
 *
 * The difference only shows when holidays lie in between: a school subject
 * on an A/B rhythm follows the calendar week and simply drops the lessons
 * during holidays. A music school lesson counts teaching weeks instead —
 * after the holidays it continues where it stopped.
 */
enum LessonCadence: string
{
    case Calendar = 'calendar';
    case SchoolWeeks = 'school_weeks';

    public function label(): string
    {
        return match ($this) {
            self::Calendar => __('by calendar week'),
            self::SchoolWeeks => __('by school weeks'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Calendar => __('Holidays cancel the lesson but do not shift the rhythm.'),
            self::SchoolWeeks => __('Holiday weeks do not count; after the holidays it continues where it stopped.'),
        };
    }
}
