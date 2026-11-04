<?php

declare(strict_types=1);

namespace Acme\Example;

use Tagwerk\PluginApi\Plugins\Catalog;
use Tagwerk\PluginApi\Plugins\Plugin;

/**
 * Loaded by Tagwerk through `extra.tagwerk.provider` in composer.json. An
 * exception in here leaves the plugin out; the app keeps running, and
 * Operations → Plugins shows why.
 */
final class ExamplePlugin extends Plugin
{
    public const KEY = 'acme/example';

    protected function registerPlugin(): void
    {
        $this->app->make(Catalog::class)->offer('widgets', self::KEY, ExampleWidget::class);
    }
}
