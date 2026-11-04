<?php

declare(strict_types=1);

namespace Tagwerk\PluginApi\Plugins;

/** How the extensions page groups what is installed. */
enum ExtensionCategory: string
{
    case Security = 'security';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Security => __('Security'),
            self::General => __('General'),
        };
    }
}
