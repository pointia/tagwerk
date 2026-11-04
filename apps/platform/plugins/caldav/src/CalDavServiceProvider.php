<?php

declare(strict_types=1);

namespace Tagwerk\CalDav;

use Tagwerk\PluginApi\Plugins\Catalog;
use Tagwerk\PluginApi\Plugins\Plugin;

final class CalDavServiceProvider extends Plugin
{
    protected function registerPlugin(): void
    {
        $this->app->make(Catalog::class)->offer('calendar', CalDavCalendarProvider::KEY, CalDavCalendarProvider::class);

        // In register: boot already loads the German texts (see example-area).
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');
    }
}
