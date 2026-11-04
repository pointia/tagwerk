<?php

declare(strict_types=1);

namespace Tagwerk\ExampleTheme;

use Tagwerk\PluginApi\Plugins\Catalog;
use Tagwerk\PluginApi\Plugins\Plugin;

/**
 * Only in development (`require-dev`): a server installs design packs
 * through `TAGWERK_PLUGINS` like any other plugin.
 */
final class ExampleThemePlugin extends Plugin
{
    public const KEY = 'tagwerk/example-theme';

    protected function registerPlugin(): void
    {
        $this->app->make(Catalog::class)->offer('themes', self::KEY, Fjord::class);
    }
}
