<?php

declare(strict_types=1);

namespace App\Enums;

enum DeviceKind: string
{
    case Wall = 'wall';
    case Mobile = 'mobile';

    public function label(): string
    {
        return match ($this) {
            self::Wall => __('Wall tablet'),
            self::Mobile => __('Mobile device'),
        };
    }
}
