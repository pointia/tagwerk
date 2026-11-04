<?php

declare(strict_types=1);

namespace Tagwerk\Bring;

use Tagwerk\PluginApi\Plugins\Catalog;
use Tagwerk\PluginApi\Plugins\Plugin;

final class BringServiceProvider extends Plugin
{
    protected function registerPlugin(): void
    {
        $this->app->make(Catalog::class)->offer('lists', 'tagwerk/bring', BringListProvider::class);
    }
}
