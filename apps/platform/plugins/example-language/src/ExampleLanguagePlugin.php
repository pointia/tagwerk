<?php

declare(strict_types=1);

namespace Tagwerk\ExampleLanguage;

use Tagwerk\PluginApi\Plugins\Catalog;
use Tagwerk\PluginApi\Plugins\Plugin;

/**
 * Only in development (`require-dev`): a server installs language packs
 * through `TAGWERK_PLUGINS` like any other plugin.
 */
final class ExampleLanguagePlugin extends Plugin
{
    public const KEY = 'tagwerk/example-language';

    protected function registerPlugin(): void
    {
        $this->app->make(Catalog::class)->offer('languages', self::KEY, French::class);
    }
}
