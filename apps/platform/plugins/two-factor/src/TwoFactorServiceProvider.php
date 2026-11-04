<?php

declare(strict_types=1);

namespace Tagwerk\TwoFactor;

use Tagwerk\PluginApi\Plugins\Catalog;
use Tagwerk\PluginApi\Plugins\Plugin;

final class TwoFactorServiceProvider extends Plugin
{
    protected function registerPlugin(): void
    {
        $this->app->make(Catalog::class)->offer('extensions', TwoFactorExtension::KEY, TwoFactorExtension::class);
    }
}
