<?php

declare(strict_types=1);

namespace App\Filament;

use Filament\Support\Contracts\HasLabel;

/**
 * The admin panel's navigation groups, in their order.
 *
 * An enum rather than strings: the panel is configured at boot, before the
 * request's language is known. The label is only read when the menu is drawn.
 */
enum NavigationGroup: string implements HasLabel
{
    case Family = 'family';
    case Everyday = 'everyday';
    case Calendar = 'calendar';
    case Setup = 'setup';
    case Integrations = 'integrations';
    case Operations = 'operations';

    public function getLabel(): string
    {
        return match ($this) {
            self::Family => __('Family'),
            self::Everyday => __('Everyday'),
            self::Calendar => __('Calendar & school'),
            self::Setup => __('Setup'),
            self::Integrations => __('Integrations'),
            self::Operations => __('Operations'),
        };
    }
}
