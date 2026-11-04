<?php

declare(strict_types=1);

namespace App\Enums;

enum MealKind: string
{
    case Lunch = 'lunch';
    case Dinner = 'dinner';

    public function label(): string
    {
        return match ($this) {
            self::Lunch => __('Lunch'),
            self::Dinner => __('Dinner'),
        };
    }
}
