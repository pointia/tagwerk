<?php

declare(strict_types=1);

namespace Tests\Fixtures\Plugins;

use RuntimeException;
use Tagwerk\PluginApi\Plugins\Catalog;
use Tagwerk\PluginApi\Plugins\Plugin;

final class FailsInBoot extends Plugin
{
    protected function registerPlugin(): void
    {
        $this->app->make(Catalog::class)->offer('photo_rules', 'acme/late', FixtureRule::class);
    }

    public function bootPlugin(): void
    {
        throw new RuntimeException('Cannot reach the service.');
    }
}
