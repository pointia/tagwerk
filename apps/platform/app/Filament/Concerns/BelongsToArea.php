<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\Household;
use App\Support\HouseholdContext;

/**
 * An admin resource or page of one area — a plugin's, typically.
 *
 * Gone from the navigation and closed when the household switched the area
 * off, and hidden without a household like every family resource. The
 * using class names its area in `area()`.
 */
trait BelongsToArea
{
    use NeedsHousehold {
        NeedsHousehold::canAccess as private canAccessInHousehold;
    }

    abstract public static function area(): string;

    public static function canAccess(): bool
    {
        return static::canAccessInHousehold() && static::areaIsOn();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::areaIsOn() && parent::shouldRegisterNavigation();
    }

    private static function areaIsOn(): bool
    {
        $household = Household::query()->find(app(HouseholdContext::class)->id());

        return $household?->has(static::area()) ?? false;
    }
}
