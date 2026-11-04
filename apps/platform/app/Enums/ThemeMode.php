<?php

declare(strict_types=1);

namespace App\Enums;

enum ThemeMode: string
{
    case Fixed = 'fixed';
    case Sun = 'sun';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('Fixed times'),
            self::Sun => __('Sunrise and sunset'),
        };
    }
}
