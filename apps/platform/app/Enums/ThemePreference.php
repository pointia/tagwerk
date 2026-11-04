<?php

declare(strict_types=1);

namespace App\Enums;

enum ThemePreference: string
{
    case Auto = 'auto';
    case Light = 'light';
    case Dark = 'dark';

    public function label(): string
    {
        return match ($this) {
            self::Auto => __('Automatic'),
            self::Light => __('Always light'),
            self::Dark => __('Always dark'),
        };
    }
}
