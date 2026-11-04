<?php

declare(strict_types=1);

namespace Tagwerk\IcsFeed;

use Filament\Panel;
use Tagwerk\IcsFeed\Filament\IcsFeedResource;
use Tagwerk\PluginApi\Plugins\ConfiguresPanel;

final class IcsFeedPanel implements ConfiguresPanel
{
    public function configurePanel(Panel $panel): void
    {
        $panel->resources([IcsFeedResource::class]);
    }
}
