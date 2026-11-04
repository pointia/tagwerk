<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Only for portions: energy needs differ from puberty on. Optional — without
 * it the portion is the mean of both.
 */
enum Gender: string
{
    case Female = 'female';
    case Male = 'male';

    public function label(): string
    {
        return match ($this) {
            self::Female => __('Female'),
            self::Male => __('Male'),
        };
    }
}
