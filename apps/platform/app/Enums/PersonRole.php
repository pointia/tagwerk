<?php

declare(strict_types=1);

namespace App\Enums;

enum PersonRole: string
{
    case Adult = 'adult';
    case Child = 'child';
    case Guest = 'guest';

    public function label(): string
    {
        return match ($this) {
            self::Adult => __('Adult'),
            self::Child => __('Child'),
            self::Guest => __('Guest'),
        };
    }
}
