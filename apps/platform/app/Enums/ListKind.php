<?php

declare(strict_types=1);

namespace App\Enums;

enum ListKind: string
{
    case Shopping = 'shopping';
    case Packing = 'packing';
    case Generic = 'generic';

    public function label(): string
    {
        return match ($this) {
            self::Shopping => __('Shopping'),
            self::Packing => __('Packing list'),
            self::Generic => __('List'),
        };
    }
}
