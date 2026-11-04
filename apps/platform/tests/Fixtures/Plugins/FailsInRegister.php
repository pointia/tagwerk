<?php

declare(strict_types=1);

namespace Tests\Fixtures\Plugins;

use RuntimeException;
use Tagwerk\PluginApi\Plugins\Catalog;
use Tagwerk\PluginApi\Plugins\Plugin;

final class FailsInRegister extends Plugin
{
    protected function registerPlugin(): void
    {
        $this->app->make(Catalog::class)->offer('photo_rules', 'acme/half', FixtureRule::class);

        throw new RuntimeException('Missing configuration.');
    }
}
