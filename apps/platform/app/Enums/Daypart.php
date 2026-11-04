<?php

declare(strict_types=1);

namespace App\Enums;

/** When in the day a task is due. Order of the cases is the order of the day. */
enum Daypart: string
{
    case Morning = 'morning';
    case Noon = 'noon';
    case Evening = 'evening';

    public function label(): string
    {
        return match ($this) {
            self::Morning => __('mornings'),
            self::Noon => __('at noon'),
            self::Evening => __('evenings'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Morning => '🌅',
            self::Noon => '☀️',
            self::Evening => '🌙',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = ucfirst($case->label());
        }

        return $options;
    }
}
